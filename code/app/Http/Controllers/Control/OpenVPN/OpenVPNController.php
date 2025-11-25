<?php

namespace App\Http\Controllers\Control\OpenVPN;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Helpers\Control\API\OpenVPN;

use App\Models\Core\Objects;

use App\Jobs\ForControlProjects\checkNewCommits;

class OpenVPNController extends Controller
{
    protected $openvpn;

	public function index(Request $request)
	{
		return view('control.openvpn.index',[
            'object' => Objects::find(120)
        ]);
	}

    public function store(Request $request)
    {
        $objectOpenVPN = Objects::find(120);
        $url= $objectOpenVPN->propertyById(31)->pivot->value;
        $login = $objectOpenVPN->propertyById(33)->pivot->value;
        $password = $objectOpenVPN->propertyById(34)->pivot->value;
        $this->openvpn = new OpenVPN($url,$login,$password,false);

        try {
            $action = $request->input('action');
            
            $response = match($action) {
                'listUsers' => $this->openvpn->listUsers(),
                'getAssignedIps' => $this->openvpn->getAssignedIps(),
                'resetAllConnections' => $this->openvpn->resetAllConnections(),
                'getServerInfo' => $this->openvpn->getServerInfo(),
                default => throw new \Exception('Неизвестное действие')
            };

            return response()->json([
                'status' => 'success',
                'action' => $action,
                'data' => $response
            ]);
            
        }catch (\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            
            return response()->json([
                'status' => 'error',
                'message' => 'OpenVPN API Error',
                'details' => [
                    'server_response' => $error['body'] ?? null,
                    'http_code' => $error['status'] ?? 500,
                    'request' => [
                        'url' => $error['requestUrl'] ?? null,
                        'method' => $error['method'] ?? 'GET'
                    ],
                    'api_config' => [
                        'base_url' => $this->$url,
                        'detected_path' => $this->apiBasePath ?? 'не определен'
                    ]
                ]
            ], $error['status'] ?? 500);
        }
    }

    // Остальные методы ресурсного контроллера можно оставить пустыми
    public function create() {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}