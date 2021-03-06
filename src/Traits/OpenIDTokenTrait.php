<?php
/**
 * Created by PhpStorm.
 * User: Felipe
 * Date: 17/10/2018
 * Time: 10:41
 */

namespace Laravel\Passport\Traits;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;

trait OpenIDTokenTrait
{
    /**
     * @param string $user_id
     * @param string $client_id
     * @param int $expires_at
     * @return \Lcobucci\JWT\Token
     */
    public function getOpenIDToken(string $user_id, string $client_id, int $expires_at)
    {
        $provider = config('auth.guards.api.provider');

        if (is_null($model = config('auth.providers.'.$provider.'.model'))) {
            throw new \RuntimeException('Unable to determine authentication model from configuration.');
        }

        $user = (new $model)->find($user_id);

        if (!$user) {
            throw new \RuntimeException('Unable to find model with specific identifier.');
        }

        $roles = $user->roles->pluck('name')->toArray();

        $token = (new Builder())->setIssuer(config('app.url'))
            ->setIssuer(str_finish(config('app.url'), '/'))
            ->setSubject($user->user_id)
            ->setAudience(implode(' ', array_prepend($this->getSecondaryAudiences(), $client_id)))
            ->setExpiration($expires_at)
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->set('name', $user->name)
            ->set('nickname', $user->nickname)
            ->set('document', $user->document)
            ->set('picture', $user->avatar)
            ->set('email', $user->email)
            ->set('gender', $user->gender)
            ->set('email_verified', method_exists($user, 'hasVerifiedEmail') ? $user->hasVerifiedEmail() : false)
            ->set('birthdate', optional($user->birth_date)->format('Y-M-D'))
            ->set('phone_number_verified', method_exists($user->phone, 'hasVerifiedPhone') ? $user->phone->hasVerifiedPhone() : false)
            ->set('roles', implode(' ', $roles))
            ->set('auth_time', Cookie::get('auth_time', now()->getTimestamp()))
            ->set('updated_at', $user->updated_at->getTimestamp());

        if (Request::has('nonce')) $token = $token->set('nonce', Request::get('nonce'));

        return $token->sign(new Sha256(), new Key('file://' . Passport::keyPath('oauth-private.key')))->getToken();
    }

    /**
     * @return array
     */
    public function getSecondaryAudiences()
    {
        return Passport::client()->where('secondary', true)->get()->pluck('id')->toArray();
    }
}
