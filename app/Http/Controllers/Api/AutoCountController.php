<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutoCountApiService;
use Illuminate\Http\Request;

class AutoCountController extends Controller
{
    protected AutoCountApiService $service;

    public function __construct(AutoCountApiService $service)
    {
        $this->service = $service;
    }

    public function orderPending(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $this->service->nextPendingOrder();

        return response($payload ? json_encode($payload) : '', 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function orderProcess(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $this->service->nextProcessOrder();

        return response($payload ? json_encode($payload) : '', 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function orderEdit(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        return response('', 200);
    }

    public function orderPaid(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $this->service->nextPaidOrder();

        return response($payload ? json_encode($payload) : '', 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function orderUpdate(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $this->service->applyDocumentUpdate($request->all());

        return response('OK');
    }

    public function orderUpdatePaid(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $this->service->applyPaidUpdate($request->all());

        return response('OK');
    }

    public function orderUpdateLog(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $this->service->logError($request->all());

        return response('OK');
    }

    public function customers(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        return response(json_encode($this->service->pendingCustomers()), 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function customersUpdate(Request $request)
    {
        if (!$this->authorized($request)) {
            return response('Unauthorized', 401);
        }

        $this->service->applyCustomerUpdate($request->all());

        return response('OK');
    }

    protected function authorized(Request $request): bool
    {
        $token = (string) config('autocount.api_token', '');
        if ($token !== '') {
            $header = (string) $request->header('X-AutoCount-Token', '');
            if (!hash_equals($token, $header)) {
                return false;
            }
        }

        return $this->service->validateBranch($request);
    }
}
