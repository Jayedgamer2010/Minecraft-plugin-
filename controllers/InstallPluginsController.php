<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use CURLFile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Models\Permission;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;

class InstallPluginsController extends Controller
{
    private string $pluginDirectory = '/plugins';

    public function __construct(
        private NodeJWTService $jwtService,
        private BlueprintAdminLibrary $blueprint
    ) {}

    public function index(Request $request, Server $server)
    {
        if (!$request->user()->can(Permission::ACTION_FILE_CREATE, $server)) {
            throw new AuthorizationException();
        }

        $category = $request->input('category');
        $pluginId = $request->input('pluginId');
        $versionId = $request->input('versionId');

        $data = $this->fetchPlugin($category, $pluginId, $versionId);
        if ($data['status'] === 'error') {
            return response()->json($data, 500);
        }

        $filePath = 'plugins/' . $data['pluginName'];
        Storage::disk('local')->put($filePath, $data['pluginFileContent']);

        $status = $this->uploadPluginToServer($server, $filePath, $request);
        Storage::disk('local')->delete($filePath);

        return response()->json($status);
    }

    private function fetchPlugin(string $category, ?string $pluginId, ?string $versionId): array
    {
        try {
            $pluginDetails = match ($category) {
                'modrinth' => $this->fetchModrinthPluginData($pluginId, $versionId),
                'curseforge' => $this->fetchCurseForgeModData($pluginId, $versionId),
                'hangar' => $this->fetchHangarPluginData($pluginId, $versionId),
                'spigotmc' => $this->fetchSpigotmcPluginData($pluginId),
                'polymart' => $this->fetchPolymartPluginData($pluginId),
            };

            if (!$pluginDetails) {
                return ['status' => 'error', 'message' => 'Invalid category'];
            }

            $pluginFileContent = file_get_contents($pluginDetails['url']);
            if ($pluginFileContent === false) {
                return ['status' => 'error', 'message' => 'Failed to download the plugin file'];
            }

            return [
                'status' => 'success',
                'pluginName' => $pluginDetails['name'],
                'pluginFileContent' => $pluginFileContent,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    private function fetchModrinthPluginData(?string $pluginId, ?string $versionId): array
    {
        if ($versionId) {
            $response = Http::get("https://api.modrinth.com/v2/version/{$versionId}");
            $pluginFile = $response->json()['files'][0];
        } else {
            $response = Http::get("https://api.modrinth.com/v2/project/{$pluginId}/version");
            $pluginFile = $response->json()[0]['files'][0];
        }

        $pluginFileUrl = $pluginFile['url'];
        $pluginName = $pluginFile['filename'];

        return ['url' => $pluginFileUrl, 'name' => $pluginName];
    }

    private function fetchCurseForgeModData(?string $pluginId, ?string $versionId): array
    {
        $apiKey = $this->blueprint->dbGet('mcplugins', 'curseforge_api_key', null); 

        $headers = [
            'Accept' => 'application/json',
            'x-api-key' => $apiKey,
        ];
        if ($versionId) {
            $response = Http::withHeaders($headers)->get("https://api.curseforge.com/v1/mods/{$pluginId}/files/{$versionId}");
            $pluginFile = $response->json()['data'];
        } else {
            $response = Http::withHeaders($headers)->get("https://api.curseforge.com/v1/mods/{$pluginId}/files");
            $pluginFile = $response->json()['data'][0];
        }

        $pluginFileUrl = $pluginFile['downloadUrl'];
        $pluginName = $pluginFile['fileName'];

        return ['url' => $pluginFileUrl, 'name' => $pluginName];
    }

    private function fetchHangarPluginData(?string $pluginId, ?string $versionId): array
    {
        if ($versionId) {
            list($versionNumber, $serverType) = explode(' - ', $versionId);
            $response = Http::get("https://hangar.papermc.io/api/v1/projects/{$pluginId}/versions/{$versionNumber}");
            $pluginFileUrl = $response['downloads'][$serverType]['downloadUrl'] ?? $response['downloads'][$serverType]['externalUrl'];
            $pluginName = $response['downloads'][$serverType]['fileInfo']['name'];
        } else {
            $response = Http::get("https://hangar.papermc.io/api/v1/projects/{$pluginId}/versions");
            $firstResult = $response['result'][0];
            $pluginFileUrl = $firstResult['downloads']['PAPER']['downloadUrl'] ?? $firstResult['downloads']['PAPER']['externalUrl'];
            $pluginName = $firstResult['downloads']['PAPER']['fileInfo']['name'];
        }
        return ['url' => $pluginFileUrl, 'name' => $pluginName];
    }

    private function fetchSpigotmcPluginData(string $pluginId): array
    {
        $response = Http::get("https://api.spiget.org/v2/resources/{$pluginId}");
        $plugin = $response->json();
        $externalUrl = $plugin['file']['externalUrl'] ?? null;

        $pluginFileUrl = str_ends_with($externalUrl, '.jar') ? $externalUrl : "https://cdn.spiget.org/file/spiget-resources/{$pluginId}.jar";
        $pluginName = $plugin['name'] . '.jar';

        return ['url' => $pluginFileUrl, 'name' => $pluginName];
    }

    private function fetchPolymartPluginData(string $pluginId): array
    {
        $downloadResponse = Http::post("https://api.polymart.org/v1/getDownloadURL", [
            'allow_redirects' => '0',
            'resource_id' => $pluginId,
        ]);
        $downloadData = $downloadResponse->json();
        $pluginFileUrl = $downloadData['response']['result']['url'];

        $response = Http::get("https://api.polymart.org/v1/getResourceInfo?resource_id={$pluginId}");
        $plugin = $response->json();
        $pluginName = $plugin['response']['resource']['title'] . '.jar';

        return ['url' => $pluginFileUrl, 'name' => $pluginName];
    }

    private function uploadPluginToServer(Server $server, string $filePath, Request $request): array
    {
        try {
            $token = $this->jwtService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setUser($request->user())
                ->setClaims(['server_uuid' => $server->uuid])
                ->handle($server->node, $request->user()->id . $server->uuid);

            $uploadUrl = sprintf(
                '%s/upload/file?token=%s&directory=%s',
                $server->node->getConnectionAddress(),
                $token->toString(),
                urlencode($this->pluginDirectory)
            );

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $uploadUrl,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => [
                    'files' => new CURLFile(storage_path('app/' . $filePath))
                ],
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json, text/plain, */*"
                ],
                CURLOPT_RETURNTRANSFER => true,
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            $errorCode = curl_errno($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($errorCode) {
                return ['status' => 'error', 'message' => 'cURL error: ' . $error];
            }

            if ($httpCode >= 400) {
                return ['status' => 'error', 'message' => 'HTTP error: ' . $httpCode];
            }

            return ['status' => 'success', 'message' => 'Plugin installed successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'An error occurred during file upload: ' . $e->getMessage()];
        }
    }
}
