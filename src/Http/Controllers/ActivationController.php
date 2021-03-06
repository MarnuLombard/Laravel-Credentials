<?php

/*
 * This file is part of Laravel Credentials by Graham Campbell.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at http://bit.ly/UWsjkb.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace GrahamCampbell\Credentials\Http\Controllers;

use Cartalyst\Sentry\Users\UserAlreadyActivatedException;
use Cartalyst\Sentry\Users\UserNotFoundException;
use GrahamCampbell\Binput\Facades\Binput;
use GrahamCampbell\Credentials\Facades\Credentials;
use GrahamCampbell\Credentials\Facades\UserRepository;
use GrahamCampbell\Throttle\Throttlers\ThrottlerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * This is the resend controller class.
 *
 * @author    Graham Campbell <graham@mineuk.com>
 * @copyright 2013-2014 Graham Campbell
 * @license   <https://github.com/GrahamCampbell/Laravel-Credentials/blob/master/LICENSE.md> Apache 2.0
 */
class ActivationController extends AbstractController
{
    /**
     * The throttler instance.
     *
     * @var \GrahamCampbell\Throttle\Throttlers\ThrottlerInterface
     */
    protected $throttler;

    /**
     * Create a new instance.
     *
     * @param \GrahamCampbell\Throttle\Throttlers\ThrottlerInterface $throttler
     *
     * @return void
     */
    public function __construct(ThrottlerInterface $throttler)
    {
        $this->throttler = $throttler;

        $this->beforeFilter('throttle.activate', ['only' => ['getActivate']]);
        $this->beforeFilter('throttle.resend', ['only' => ['postResend']]);

        parent::__construct();
    }

    /**
     * Activate an existing user.
     *
     * @param int    $id
     * @param string $code
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *
     * @return \Illuminate\Http\Response
     */
    public function getActivate($id, $code)
    {
        if (!$id || !$code) {
            throw new BadRequestHttpException();
        }

        try {
            $user = Credentials::getUserProvider()->findById($id);

            if (!$user->attemptActivation($code)) {
                return Redirect::to(Config::get('graham-campbell/core::home', '/'))
                    ->with('error', 'There was a problem activating this account. Please contact support.');
            }

            $user->addGroup(Credentials::getGroupProvider()->findByName('Users'));

            return Redirect::route('account.login')
                ->with('success', 'Your account has been activated successfully. You may now login.');
        } catch (UserNotFoundException $e) {
            return Redirect::to(Config::get('graham-campbell/core::home', '/'))
                ->with('error', 'There was a problem activating this account. Please contact support.');
        } catch (UserAlreadyActivatedException $e) {
            return Redirect::route('account.login')
                ->with('warning', 'You have already activated this account. You may want to login.');
        }
    }

    /**
     * Display the resend form.
     *
     * @return \Illuminate\View\View
     */
    public function getResend()
    {
        return View::make('graham-campbell/credentials::account.resend');
    }

    /**
     * Queue the sending of the activation email.
     *
     * @return \Illuminate\Http\Response
     */
    public function postResend()
    {
        $input = Binput::only('email');

        $val = UserRepository::validate($input, array_keys($input));
        if ($val->fails()) {
            return Redirect::route('account.resend')->withInput()->withErrors($val->errors());
        }

        $this->throttler->hit();

        try {
            $user = Credentials::getUserProvider()->findByLogin($input['email']);

            if ($user->activated) {
                return Redirect::route('account.resend')->withInput()
                    ->with('error', 'That user is already activated.');
            }

            $code = $user->getActivationCode();

            $mail = [
                'url'     => URL::to(Config::get('graham-campbell/core::home', '/')),
                'link'    => URL::route('account.activate', ['id' => $user->id, 'code' => $code]),
                'email'   => $user->getLogin(),
                'subject' => Config::get('graham-campbell/core::name').' - Activation',
            ];

            Mail::queue('graham-campbell/credentials::emails.resend', $mail, function ($message) use ($mail) {
                $message->to($mail['email'])->subject($mail['subject']);
            });

            return Redirect::route('account.resend')
                ->with('success', 'Check your email for your new activation email.');
        } catch (UserNotFoundException $e) {
            return Redirect::route('account.resend')
                ->with('error', 'That user does not exist.');
        }
    }
}
