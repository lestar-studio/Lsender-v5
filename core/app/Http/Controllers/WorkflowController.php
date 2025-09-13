<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\MessageTemplate;
use App\Models\Session;
use App\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        if($request->ajax()) {
            $auth = auth()->user();
            $table = Workflow::where([
                'user_id' => $auth->id,
            ])
            ->with('template')
            ->with('session')
            ->orderBy('created_at', 'desc')->get();

            return datatables()->of($table)
                ->addColumn('responsive_id', function () {
                    return;
                })
                ->editColumn('title', function ($row) {
                    return $row->title;
                })
                ->editColumn('device',function($row){
                    return $row->session->session_name  . ' ( ' . $row->session->whatsapp_number . ' )';
                })
                ->editColumn('template',function($row){
                    return $row->template->title;
                })
                ->editColumn('created_at',function($row){
                    return $row->created_at->format('d M Y H:i');
                })
                ->addColumn('action', function ($row) {
                    $btn = '<a href="'. route('workflow.edit', $row->id) .'"  class="btn btn-icon btn-label-primary me-1"><span class="ti ti-eye"></span></a>';
                    $btn .= '<a href="javascript:void(0)" class="btn btn-icon btn-label-danger is-btn-delete" data-id="' . $row->id . '"><span class="ti ti-trash-x"></span></a>';
                    return $btn;
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        } else {
            $data['templates'] = MessageTemplate::where('user_id', auth()->user()->id)->get();
            $data['sessions'] = Session::where('user_id', auth()->user()->id)->get();
            return Lyn::view('workflow.index', $data);
        }
    }

    public function store(Request $request)
    {
        if(!$request->ajax()) return abort(404);
        $request->validate([
            'title' => 'required',
            'session_id' => 'required',
            'message_template_id' => 'required',
        ]);

        try {
            $table = new Workflow();
            $table->user_id = auth()->user()->id;
            $table->session_id = $request->session_id;
            $table->slug = \Str::uuid();
            $table->title = $request->title;
            $table->message_template_id = $request->message_template_id;
            $table->variables = [];
            $table->save();
            return response()->json(['message' => 'Message template created successfully', 'url' => route('workflow.edit', $table->id)], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function edit($id) {
        $data['row'] = Workflow::where([
            'id' => $id,
            'user_id' => auth()->user()->id,
        ])->first();
        if(!$data['row']) return abort(404);
        $data['templates'] = MessageTemplate::where('user_id', auth()->user()->id)->get();
        $data['sessions'] = Session::where('user_id', auth()->user()->id)->get();
        return Lyn::view('workflow.edit', $data);
    }

    public function capture_dev($id) {
        $table = Workflow::where([
            'id' => $id,
            'user_id' => auth()->user()->id,
        ])->first();
        if(!$table) return response()->json(['message' => 'Workflow not found'], 400);
        $table->status = 'dev';
        $table->save();
        return response()->json(['message' => 'Workflow updated successfully'], 200);
    }

    public function update_general(Request $request) {
        try {
            $workflow = Workflow::where('id', $request->id)
                ->where('user_id', auth()->user()->id)
                ->first();
            if (!$workflow) throw new \Exception('Workflow not found');
            $workflow->title = $request->title;
            $workflow->session_id = $request->session_id;
            $workflow->message_template_id = $request->message_template_id;
            $workflow->save();
            return response()->json(['message' => 'Workflow updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update_variable(Request $request) {
        try {
            $workflow = Workflow::where('id', $request->id)
                ->where('user_id', auth()->user()->id)
                ->first();
            if (!$workflow) throw new \Exception('Workflow not found');
            $workflow->variables = $request->variables;
            $workflow->save();
            return response()->json(['message' => 'Workflow updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function delete($id) {
        try {
            $workflow = Workflow::where('id', $id)
                ->where('user_id', auth()->user()->id)
                ->first();
            if (!$workflow) throw new \Exception('Workflow not found');
            $workflow->delete();
            return response()->json(['message' => 'Workflow deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
