<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Larravel Passport の認証ページヘユーザーをリダイレクト
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('laravelpassport')->redirect();
    }

    /**
     * Larravel Passport からユーザー情報を取得
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        // コンテナ内外で Laravel Passport サーバの FQDN が変わるため
        $clientId = config('services.laravelpassport.client_id');
        $clientSecret = config('services.laravelpassport.client_secret');
        $redirectUrl = config('services.laravelpassport.redirect');
        $additionalProviderConfig = ['host' => 'https://nginx'];
        $config = new \SocialiteProviders\Manager\Config($clientId, $clientSecret, $redirectUrl, $additionalProviderConfig);

        $user = Socialite::driver('laravelpassport')->setConfig($config)->stateless()->user();

        logger(print_r($user, true));
        return json_encode($user);
    }
}
