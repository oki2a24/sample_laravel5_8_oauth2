<?php

namespace App\Http\Controllers;

use GuzzleHttp;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    /**
     * Laravel Passport でログイン、のページを返す。
     */
    public function index()
    {
        return view('sample.index', []);
    }

    /**
     * Laravel Passport へ許可のリダイレクトをする。
     */
    public function redirect()
    {
        $query = http_build_query([
            'client_id' => config('services.laravelpassport.client_id'),
            'redirect_uri' => config('services.laravelpassport.redirect'),
            'response_type' => 'code',
            'scope' => '',
        ]);

        logger($query);

        return redirect(config('services.laravelpassport.auth').'?'.$query);
    }

    /**
     * Laravel Passport へ POST リクエストし、許可コードからアクセストークンへの変換を行う。
     */
    public function callback(Request $request)
    {
        $http = new GuzzleHttp\Client;

        $response = $http->post(config('services.laravelpassport.token'), [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.laravelpassport.client_id'),
                'client_secret' => config('services.laravelpassport.client_secret'),
                'redirect_uri' => config('services.laravelpassport.redirect'),
                'code' => $request->code,
            ],
            'verify' => false, // TODO 開発環境のみ付与
        ]);

        logger(print_r((string) $response->getBody(), true));

        return json_decode((string) $response->getBody(), true);
    }
}
