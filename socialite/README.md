# クライアントアプリ (Laravel Socialite) から Laravel Passport でログイン
## 前提
[ここ](../client/README.md) と同じです。

## 実装
- app/Http/Controllers/Auth/LoginController.php ログインするための処理はここに集約しました。
- config/services.php Laravel Socialite を使うための設定を書きました。
- routes/web.php ログインするためのルートを集約しました。

設定は .env に書きますが、例えば次のようになりました。

```
LARAVELPASSPORT_KEY=4
LARAVELPASSPORT_SECRET=z7oC3Qe2nzXwEN2jD76nAqhwfZMUzloBA5AijAUy
LARAVELPASSPORT_REDIRECT_URI=https://localhost:4434/login/laravelpassport/callback
```
  
## 動かすために学んだこと
### リダイレクト先の URL の FQDN を設定したい
何も設定しない場合、認証するためのリダイレクト URL は次となった。

https://localhost:4434/oauth/authorize?client_id=2&redirect_uri=https%3A%2F%2Flocalhost%3A4434%2Fpassport%2Fcallback&scope=&response_type=code&state=nICP1wSK6mpWBHyKqlNOsGNZ0DBd4CueErfIiuMZ

リダイレクト先の URL がクライアントサイトとなっているので、これを Laravel Paspport のサイトにしたい。
https://github.com/SocialiteProviders/Providers/blob/master/src/LaravelPassport/Provider.php#L125
の host の値がそれに当たる。
https://github.com/SocialiteProviders/Providers/blob/master/src/LaravelPassport/Provider.php#L32
に設定できればよいがどうすればよいか？
どうやら、
https://github.com/SocialiteProviders/Providers/blob/master/src/LaravelPassport/Provider.php#L29
の additionalConfigKeys の値を設定するには、 config/services.php で設定すればよいだけだった。

```php
    'laravelpassport' => [
        'client_id' => env('LARAVELPASSPORT_KEY'),
        'client_secret' => env('LARAVELPASSPORT_SECRET'),
        'redirect' => env('LARAVELPASSPORT_REDIRECT_URI'),
        'host' => 'https://localhost',
    ],
```

これを突き止めるために、 config の値をみたかったので、パッケージを強引に修正してみられるようにした。これはもちろん一時的なそち。
https://github.com/SocialiteProviders/Manager/blob/e3e8e78b9a3060801cd008941a0894a0a0c479e1/src/ConfigTrait.php#L44
具体的にはこのメソッドのスコープを public にした。

### 凡ミス。クライアントアプリが異なる、コールバック URL が異なるなら、新たに Laravel Passport クライアントを作成すること
https://localhost/oauth/authorize?client_id=2&redirect_uri=https%3A%2F%2Flocalhost%3A4434%2Fpassport%2Fcallback&response_type=code&scope=&state=nvaGAyXkED4mO1tqvqYXVPlODSxsxwaTnk6G4ynE
{"error":"invalid_client","error_description":"Client authentication failed","message":"Client authentication failed"}
クライアントアプリが異なるので、別の Laravel Passport クライアントを発行する必要があった。

Client ID: 4
Client secret: z7oC3Qe2nzXwEN2jD76nAqhwfZMUzloBA5AijAUy

また他に気が付いたこととして、 url に state があった。もしかして、 Laravel Passport へリダイレクトするときに、 state をつけると、戻ってくるときに state をそのままの値で返してくれるのかもしれない。要検証。 <- 多分そう。

### Laravel\Socialite\Two\InvalidStateException になる
リダイレクト前に、セッションにキーが state 、 値が ランダム値を保存している。
コールバックに戻ってきたとき、 state の値が消えているため、コールバック時の URL のパラメータについていた state と一致させることができず、発生している。

[SocialiteにおけるInvalidStateExceptionって - Qiita](https://qiita.com/chtzmrtshgh/items/84817942255d3d5dff45)

ただし、セッション管理がファイルでは不可能で、 DB である必要があるようだ。
もう一つの解決方法は、 stateless にすること。こちらでまずやってみる。セキュリティーが甘くなってしまう。広く公開するクライアントアプリの場合は不可だが、内部的な場合なので見逃しておく。

### Laravel Passport の URL が変化する
GuzzleHttp\Exception\ConnectException cURL error 7: Failed to connect to localhost port 443: Connection refused (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)
単純に接続できていない。
redirect メソッドで指定するときの Laravel Passport の URL は Docker ホストから見たものなので localhost で、
Socialite 内部から curl するときの Laravel Passport の URL は Docker コンテナから見たものなので nginx となる。
よって、設定し直す。

### 自己署名の証明証でも無理やり処理する
GuzzleHttp\Exception\RequestException cURL error 60: SSL certificate problem: self signed certificate (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)
"vendor/laravel/socialite/src/Two/AbstractProvider.php"
https://github.com/laravel/socialite/blob/4.0/src/Two/AbstractProvider.php#L263-L266
を次のようにした。公式ドキュメントにもあるが、これは本当はやっちゃダメ。

- [verify Request Options — Guzzle Documentation](http://docs.guzzlephp.org/en/stable/request-options.html#verify)

どのみち、依存パッケージの中身を書き換えているので、本番では使えない。

```php
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFields($code),
            'verify' => false,
        ]);
```

GuzzleHttp\Exception\RequestException cURL error 60: SSL certificate problem: self signed certificate (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)
同様のエラー
https://github.com/SocialiteProviders/Providers/blob/master/src/LaravelPassport/Provider.php#L76-L79

```php
        $response = $this->getHttpClient()->get($this->getLaravelPassportUrl('userinfo_uri'), [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
            'verify' => false,
        ]);
```

後日、 HTTPS ではなく、 HTTP ならばこの問題は発生しないことに気がつき、こちらの通信を使うようにして、この問題を回避した。

### デバッグで役に立ったログ出力
```php
        logger('session all');
        logger($request->session()->all());
        logger('request state');
        logger($request->input('state'));
```
