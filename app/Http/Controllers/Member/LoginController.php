<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\LocaleService;
use App\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function getForm(Request $request)
    {
        return view('login');
    }

    public function login(Request $request, LocaleService $localeService)
    {
        $data = $this->validateLogin($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $login_data = [
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (Auth::guard('web')->attempt($login_data)) {
            $user = Auth::guard('web')->user();
            if (!$user->hasCompletedRegistration()) {
                Auth::guard('web')->logout();
                return back()->with('error', 'Please complete registration using the link sent to you by admin.')->withInput();
            }
            if ($user->status !== User::$user_status['active']) {
                Auth::guard('web')->logout();
                return back()->with('error', 'Your account is not active. Please contact us for assistance.')->withInput();
            }

            $localeService->syncSessionFromUser($user);

            return redirect(route('member.products'));
        } else {
            // Authentication failed
            return back()->with('error', 'Email or password is incorret')->withInput();
        }
    }

    public function validateLogin(Request $request)
    {
        $rules = [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        try {
            $data = $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }

        return $data;
    }

    public function fastLogin(Request $request, $login_code)
    {
        $login_code = $this->resolveFastLoginToken($login_code);
        if ($login_code === null) {
            return redirect()->to('/')->with(['error' => 'Invalid Login. Please contact us for more.']);
        }

        $user = User::where('login_code', $login_code)->first();
        if ($user) {
            if (!$user->hasCompletedRegistration()) {
                return redirect()->to('/')->with(['error' => 'Please complete registration using your invitation link first.']);
            }
            if ($user->status !== User::$user_status['active']) {
                return redirect()->to('/')->with(['error' => 'Your account is not active. Please contact us for assistance.']);
            }
            Auth::guard('web')->login($user);
            return redirect(route('member.products'));
        } else {
            // Authentication failed
            return redirect()->to('/')->with(['error' => 'Account Not Found.']);
        }
    }

    private function resolveFastLoginToken(string $token): ?string
    {
        try {
            return Crypt::decryptString($token);
        } catch (DecryptException $e) {
            return preg_match('/^[A-Za-z0-9]+$/', $token) ? $token : null;
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        Auth::guard('web')->logout();

        // Redirect to a specific page after logout
        return redirect('/');
    }
}
