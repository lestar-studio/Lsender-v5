<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\History;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SingleSender extends Controller
{
    public function __construct()
    {
        $this->url = config('app.base_node');
    }

    public function index()
    {
        if (!session()->get('main_device')) {
            return Lyn::view('nodevice');
        }
        return Lyn::view('singlesend.index');
    }

    public function store(Request $request)
    {
        if (!session()->get('main_device')) return response()->json(['message' => 'No device selected.'], 400);
        $request->validate([
            'receiver' => 'required',
            'message_type' => 'required',
        ]);

        $device = Session::where(['id' => session()->get('main_device'), 'user_id' => auth()->user()->id])->first();
        if (!$device) return response()->json(['message' => 'No device selected.']);
        $pars = array(
            "api_key" => $device->api_key,
            "receiver" => $this->validate_receiver("$request->receiver"),
        );

        if ($request->message_type == 'text') {
            $request->validate([
                'message' => 'required',
            ]);
            $pars['data'] = array(
                'message' => $request->message,
            );
            try {
                $response = Http::post($this->url . '/api/send-message', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => 'Message sent.']);
                } else {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send message.')], 500);
                }
            } catch (\Exception $e) {
                History::record($request, [
                    'from' => 'single',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else if ($request->message_type == 'media') {
            $request->validate([
                'media' => 'required',
                'media_type' => 'required',
            ]);
            $pars['waiting'] = 3000;
            $pars['data'] = array(
                'url' => $request->media,
                'media_type' => $request->media_type,
                'caption' => $request->message ?? '',
            );
            try {
                $response = Http::post($this->url . '/api/send-media', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Media sent.')]);
                } else {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send message.')], 500);
                }
            } catch (\Exception $e) {
                History::record($request, [
                    'from' => 'single',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else if ($request->message_type == 'button') {
            $request->validate([
                'message' => 'required',
                'footer' => 'required',
            ]);

            $buttons = [];

            foreach ($request->btn_display as $key => $val) {
                $buttons[] = array(
                    "display" => $request->btn_display[$key],
                    "id" => $request->btn_id[$key],
                );
            }

            $pars['data'] = array(
                'message' => $request->message,
                'footer' => $request->footer,
                'buttons' => $buttons,
            );

            try {
                $response = Http::post($this->url . '/api/send-button', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Media sent.')]);
                } else {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send message.')], 500);
                }
            } catch (\Exception $e) {
                History::record($request, [
                    'from' => 'single',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else if ($request->message_type == 'list') {
            $request->validate([
                'message' => 'required',
                'footer' => 'required',
            ]);

            $sections = [];
            $first = true;
            foreach ($request->btn_display as $key => $val) {
                if ($request->type[$key] == 'section') {
                    if ($first) {
                        $first = false;
                    }
                    $sections[] = array(
                        "title" => $request->btn_display[$key],
                        "rows" => [],
                    );
                } else if ($request->type[$key] == 'option') {
                    if ($first) {
                        $sections[] = array(
                            "rows" => [],
                        );
                        $first = false;
                    }
                    $sections[count($sections) - 1]['rows'][] = array(
                        "title" => $request->btn_display[$key],
                        "rowId" => $request->btn_id[$key] ?? '',
                    );
                }
            }

            $pars['data'] = array(
                'title' => $request->title ?? '',
                'message' => $request->message,
                'footer' => $request->footer,
                'buttonText' => $request->button_text ?? 'Click Here',
                'sections' => $sections,
            );

            try {
                $response = Http::post($this->url . '/api/send-listmsg', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'List Button sent.')]);
                } else {
                    History::record($request, [
                        'from' => 'single',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send List Button.')], 500);
                }
            } catch (\Exception $e) {
                History::record($request, [
                    'from' => 'single',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
    }
    public function apiStore(Request $request)
    {
        $pars = array(
            "api_key" => $request->api_key,
            "receiver" => $this->validate_receiver("$request->receiver"),
        );

        if ($request->message_type == 'text') {
            $request->validate([
                'data.message' => 'required',
            ]);
            $pars['data'] = array(
                'message' => $request->data['message'],
            );
            try {
                $response = Http::post($this->url . '/api/send-message', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::apiRecord($request, [
                        'from' => 'api',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => 'Message sent.']);
                } else {
                    History::apiRecord($request, [
                        'from' => 'api',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send message.')], 500);
                }
            } catch (\Exception $e) {
                History::apiRecord($request, [
                    'from' => 'api',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        } else if ($request->message_type == 'media') {
            $request->validate([
                'data.media_type' => 'required',
                'data.media' => 'required',
            ]);
            $pars['waiting'] = 3000;
            $pars['data'] = array(
                'url' => $request->data['media'],
                'media_type' => $request->data['media_type'],
                'caption' => $request->data['caption'] ?? '',
            );
            try {
                $response = Http::post($this->url . '/api/send-media', $pars);
                $response = $response->json();
                if ($response['status'] == 'success') {
                    History::apiRecord($request, [
                        'from' => 'api',
                        'status' => 'sent'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Media sent.')]);
                } else {
                    History::apiRecord($request, [
                        'from' => 'api',
                        'status' => 'failed'
                    ]);
                    return response()->json(['message' => ($response['message'] ?? 'Failed to send message.')], 500);
                }
            } catch (\Exception $e) {
                History::apiRecord($request, [
                    'from' => 'api',
                    'status' => 'failed'
                ]);
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
    }

    public function validate_receiver($number)
    {
        $cleaned_number = preg_replace('/[^0-9]/', '', $number);

        if (substr($cleaned_number, 0, 1) === '+') {
            return '62' . substr($cleaned_number, 1);
        } elseif (substr($cleaned_number, 0, 1) === '0') {
            return '62' . substr($cleaned_number, 1);
        } else {
            return $cleaned_number;
        }
    }
}
