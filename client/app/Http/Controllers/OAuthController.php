<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OAuthController extends Controller
{
    public function redirect()
    {
        $queries = http_build_query([
            'client_id' => config('services.oauth_server.client_id'),
            'redirect_uri' => config('services.oauth_server.redirect'),
            'response_type' => 'code',
            'scope' => 'ameise/mitarbeiterwebservice',
            'state' => 'abc123456'
        ]);

        return redirect(config('services.oauth_server.uri') . '/oauth2/auth?' . $queries);
    }

    public function callback(Request $request)
    {
        $client_id = config('services.oauth_server.client_id');
        $client_secret = config('services.oauth_server.client_secret');
        $authorization = base64_encode("$client_id:$client_secret");

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $authorization,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post(config('services.oauth_server.uri') . '/oauth2/token', [

            'grant_type' => 'authorization_code',
            // 'client_id' => config('services.oauth_server.client_id'),
            // 'client_secret' => config('services.oauth_server.client_secret'),
            'redirect_uri' => config('services.oauth_server.redirect'),
            'scope' => 'ameise/mitarbeiterwebservice',
            'state' => 'abc123456',
            // 'code' => $request->code
            'code' => 'GpowiFbSY0kmrchPEXLq86A-aB1mEGm9sSAl5zwizA8.RPp_RsS2yqiQWNC6USVYDKpMpowlMqmLoen4kuH8IZA'
        ]);

        $response = $response->json();

        // offline_access","offline","openid"
        //Scope:openid
        //--------
        // [access_token]
        // [expires_in]
        // [id_token]
        // [scope]
        // [token_type]
        //--------

        //Scope:offline_access
        //--------
        // [access_token]
        // [expires_in] 
        // [refresh_token]
        // [scope]
        // [token_type]   
        //--------
        //Scope:ameise/mitarbeiterwebservice
        //--------
        // [access_token]
        // [expires_in]
        // [scope]
        // [token_type]
        //--------


        // print_r($response);
        // exit;
        $request->user()->token()->delete();

        if ($response['scope'] == 'ameise/mitarbeiterwebservice') {
            $request->user()->token()->create([
                'access_token' => $response['access_token'],
                'expires_in'   => $response['expires_in'],
                'scope'        => $response['scope'],
                'token_type'   => $response['token_type']
            ]);
        } elseif ($response['scope'] == 'offline_access') {
            $request->user()->token()->create([
                'access_token' => $response['access_token'],
                'expires_in'   => $response['expires_in'],
                'refresh_token' => $response['refresh_token']
            ]);
        } elseif ($response['scope'] == 'openid') {
            $request->user()->token()->create([
                'access_token' => $response['access_token'],
                'expires_in'   => $response['expires_in'],
                'id_token'     => $response['id_token'],
                'scope'        => $response['scope'],
                'token_type'   => $response['token_type']
            ]);
        }

        // $request->user()->token()->create([
        //     'access_token' => $response['access_token'],
        //     'expires_in' => $response['expires_in'],
        //     'refresh_token' => $response['refresh_token']
        // ]);

        return redirect('/home');
    }

    public function refresh(Request $request)
    {
        $response = Http::post(config('services.oauth_server.uri') . '/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->user()->token->refresh_token,
            'client_id' => config('services.oauth_server.client_id'),
            'client_secret' => config('services.oauth_server.client_secret'),
            'redirect_uri' => config('services.oauth_server.redirect'),
            'scope' => 'view-posts'
        ]);

        if ($response->status() !== 200) {
            $request->user()->token()->delete();

            return redirect('/home')
                ->withStatus('Authorization failed from OAuth server.');
        }

        $response = $response->json();
        $request->user()->token()->update([
            'access_token' => $response['access_token'],
            'expires_in' => $response['expires_in'],
            'refresh_token' => $response['refresh_token']
        ]);

        return redirect('/home');
    }
}