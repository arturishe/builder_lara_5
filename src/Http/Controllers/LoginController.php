<?php

namespace Vis\Builder;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;

class LoginController extends Controller
{
    public function showLogin()
    {
        try {
            if (Sentinel::check()) {
                return Redirect::to(config('builder.admin.uri'));
            }
        } catch (\Cartalyst\Sentinel\Checkpoints\NotActivatedException $e) {
            Session::flash('login_not_found', 'Пользователь не активирован');
            Sentinel::logout();

            return Redirect::route('login_show');
        }

        return view('admin::vis-login');
    }

    // end showLogin

    public function postLogin()
    {
        if ($this->validation()) {
            try {
                $user = Sentinel::authenticate(
                    [
                        'email' => Input::get('email'),
                        'password' => Input::get('password'),
                    ]
                );

                if (! $user) {
                    Session::flash('login_not_found', 'Пользователь не найден');

                    return Redirect::route('login_show');
                }

                if (config('builder.login.on_login') && config('builder.login.on_login')()) {
                    return config('builder.login.on_login')();
                }

                return Redirect::intended(config('builder.admin.uri'));
            } catch (\Cartalyst\Sentinel\Checkpoints\ThrottlingException $e) {
                Session::flash('login_not_found', 'Превышено количество возможных попыток входа');

                return Redirect::route('login_show');
            } catch (\Cartalyst\Sentinel\Checkpoints\NotActivatedException $e) {
                Session::flash('login_not_found', 'Пользователь не активирован');

                return Redirect::route('login_show');
            }
        } else {
            Session::flash('login_not_found', 'Некорректные данные запроса');

            return Redirect::route('login_show');
        }
    }

    // end

    public function doLogout()
    {
        Sentinel::logout();
        $this->clearSessionsAdmin();

        return Redirect::route('login_show');
    }

    // end doLogout

    private function validation()
    {
        $rules = [
            'email' => 'required|email|max:50',
            'password'=> 'required|min:6|max:20',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return false;
        } else {
            return true;
        }
    }

    private function clearSessionsAdmin()
    {
        Session::forget('table_builder');
    }
}
