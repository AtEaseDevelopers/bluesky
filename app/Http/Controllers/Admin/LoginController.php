<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function showForm()
    {
        if (Auth::guard('web_admin')->check()) {
            return $this->redirectToLanding(Auth::guard('web_admin')->user());
        }

        return view('admin.login');
    }

    /**
     * Login function.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function login(Request $request, LocaleService $localeService)
    {
        $data = $this->validateLogin($request);
        if (isset($data['error']) && $data['error']){
            return back()->withInput()->withErrors($data['field_err']);
        }

        $login_data = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
        if (Auth::guard('web_admin')->attempt($login_data)) {
            $admin = Auth::guard('web_admin')->user();
            if (!$admin->isActive()) {
                Auth::guard('web_admin')->logout();

                return back()->with('error', 'Your account is inactive. Please contact a superadmin.')->withInput();
            }

            $localeService->syncSessionFromUser($admin);

            return $this->redirectToLanding($admin);
        } else {
            // Authentication failed
            return back()->with('error', 'Account Not Found.')->withInput();
        }
    }

    /**
     * Redirect an authenticated admin to the first module they can access.
     *
     * Admins whose role cannot view the dashboard would otherwise land on a
     * 403 page; send them to their first accessible module instead. When no
     * module is accessible, log them out with an explanation.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToLanding($admin)
    {
        $landing = $admin->defaultLandingRoute();

        if ($landing === null) {
            Auth::guard('web_admin')->logout();

            return redirect(route('admin.login'))
                ->with('error', 'Your account has no accessible modules. Please contact a superadmin.');
        }

        return redirect(route($landing));
    }

    /**
     * Validate Login validation.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function validateLogin(Request $request)
    {
        $rules = [
            'username' => ['required', 'string'],
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

    /**
     * Login function.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function logout(Request $request)
    {
        Auth::guard('web_admin')->logout();

        // Redirect to a specific page after logout
        return redirect(route('admin.login'));
    }
}
