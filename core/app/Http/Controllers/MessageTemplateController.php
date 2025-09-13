<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        if($request->ajax()){
            $auth = auth()->user();
            $table = MessageTemplate::where([
                'user_id' => $auth->id,
            ])->orderBy('created_at', 'desc')->get();

            return datatables()->of($table)
                ->addColumn('responsive_id', function () {
                    return;
                })
                ->editColumn('title', function ($row) {
                    return $row->title;
                })
                ->editColumn('created_at',function($row){
                    return $row->created_at->format('d M Y H:i');
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="javascript:void(0)"  class="btn btn-icon btn-label-primary me-1 is-btn-edit" data-id="' . $row->id . '"><span class="ti ti-edit"></span></a>';
                    $btn .= '<a href="javascript:void(0)" class="btn btn-icon btn-label-danger is-btn-delete" data-id="' . $row->id . '"><span class="ti ti-trash-x"></span></a>';
                    return $btn;
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        } else {
            return Lyn::view('message-template.index');
        }
    }

    public function store(Request $request)
    {
        if(!$request->ajax()) return abort(404);
        $request->validate([
            'title' => 'required',
            'message' => 'required',
        ]);

        try {
            $message = new MessageTemplate();
            $message->user_id = auth()->user()->id;
            $message->title = $request->title;
            $message->message = $request->message;
            $message->save();
            return response()->json(['message' => 'Message template created successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function edit(Request $request, $id)
    {
        if (!$request->ajax()) return response()->json(['status' => 'error', 'message' => 'Invalid request'], 400);
        $table = MessageTemplate::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->first();
        if (!$table) return response()->json(['status' => 'error', 'message' => 'Message template not found'], 404);
        return response()->json([
            'status' => 'success',
            'data' => $table
        ], 200);
    }

    public function update(Request $request)
    {
        if(!$request->ajax()) return abort(404);
        $request->validate([
            'id' => 'required',
            'title' => 'required',
            'message' => 'required',
        ]);

        try {
            $message = MessageTemplate::where('id', $request->id)
                ->where('user_id', auth()->user()->id)
                ->first();
            if (!$message) throw new \Exception('Message template not found');
            $message->user_id = auth()->user()->id;
            $message->title = $request->title;
            $message->message = $request->message;
            $message->save();
            return response()->json(['message' => 'Message template created successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function delete($id) {
        try {
            $message = MessageTemplate::where('id', $id)
                ->where('user_id', auth()->user()->id)
                ->first();
            if (!$message) throw new \Exception('Message template not found');
            $message->delete();
            return response()->json(['message' => 'Message template deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
