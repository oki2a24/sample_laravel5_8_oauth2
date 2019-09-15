# クライアントアプリ (Laravel) から Laravel Passport でログイン
## 前提
- OAuth2 の知識が必要です。
  - [一番分かりやすい OAuth の説明 - Qiita](https://qiita.com/TakahikoKawasaki/items/e37caf50776e00e733be)
- 本来必要なのは OAuth2 の認可機能ではなく、ただの認証機能で、それは OpenID Connect によって実現できます。ただ、サンプル構築後に動きを確認したところ、 OpenID Connect の動きを Laravel Passport は充分に満たしているように思えます。
  - [一番分かりやすい OpenID Connect の説明 - Qiita](https://qiita.com/TakahikoKawasaki/items/498ca08bbfcc341691fe)

## 具体的な実装
- client/app/Http/Controllers/SampleController.php
  ログインするための処理は全てここに集約しました。
- client/config/services.php
  - Laravel Passport でログインするための、 client id 等の設定をまとめました。
- client/resources/views/sample/index.blade.php
  - Laravel Passport でログインするボタンを設置しました。
- client/routes/web.php
  - ログインするために必要なルートを定義しました。

設定は、 .env に書きますが、たとえば次のようになりました。
注) LARAVELPASSPORT_AUTH_URI と LARAVELPASSPORT_TOKEN_URI の FQDN は Laravel Passport サーバとします。したがって本来ならば同じとなります。今回は、 Docker を使ってサンプルを構築した関係で、リダイレクトするときは Docker ホストから見た FQDN を、Guzzle でリクエストするときは Docker コンテナから見た FQDN となったため、異なっています。

```
LARAVELPASSPORT_KEY=2
LARAVELPASSPORT_SECRET=eI7yOgFrNn9CiMRmL7i7inSMyjDoluGetbOLPXfn
LARAVELPASSPORT_REDIRECT_URI=https://localhost:4433/sample/callback
LARAVELPASSPORT_AUTH_URI=https://localhost/oauth/authorize
LARAVELPASSPORT_TOKEN_URI=https://nginx/oauth/token
```

## 作ったものを動かしてみてわかったこと
"クライアントアプリで、Laravel Passport でログイン" -> ログイン画面 -> Authorize -> クライアントアプリ
Laravel Passport 側で、 Remember Me にチェックを打っておくと、次の "Laravel Passport でログイン" の認証処理をすっ飛ばせる。

クライアントアプリへ認証からコールバックで戻ってきて、次は Laravel Passport へ POST リクエストし許可コードからアクセストークンへの変換を行うという時、リクエストまでの時間が長すぎると次のエラーになった。

```
[2019-09-14 23:34:11] local.ERROR: Client error: `POST https://nginx/oauth/token` resulted in a `400 Bad Request` response:
{"error":"invalid_request","error_description":"The request is missing a required parameter, includes an invalid paramet (truncated...)
 {"exception":"[object] (GuzzleHttp\\Exception\\ClientException(code: 400): Client error: `POST https://nginx/oauth/token` resulted in a `400 Bad Request` response:
 {\"error\":\"invalid_request\",\"error_description\":\"The request is missing a required parameter, includes an invalid paramet (truncated...)
  at /var/www/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php:113)
 [stacktrace]
#0 /var/www/vendor/guzzlehttp/guzzle/src/Middleware.php(66): GuzzleHttp\\Exception\\RequestException::create(Object(GuzzleHttp\\Psr7\\Request), Object(GuzzleHttp\\Psr7\\Response))
```

## 足りていないところ
- Laravel Passport でログインが達成できた。これで access_token を得られたが、これはどう保存したらよいだろうか? Cookie? Session? データベース?
  - [JavaScript - 認証用トークンはクッキーに保存すべき？ローカルストレージに保存すべき？｜teratail](https://teratail.com/questions/84388)
    結論。どちらでもよい。どちらでも同じということを意味せず、ケースバイケースという意味。
    後は、実践が足りていない。

