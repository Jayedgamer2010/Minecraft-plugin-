<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Models\Permission;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;


class PluginVersionsController extends Controller
{
    public function __construct(
        private BlueprintAdminLibrary $blueprint
    ) {}

    public function index(Request $request, Server $server)
    {
        if (!$request->user()->can(Permission::ACTION_FILE_READ, $server)) {
            throw new AuthorizationException();
        }
        $category = $request->query('category');
        $pluginId = $request->query('pluginId');

        $url = $this->getUrl($category, $pluginId);
        $response = Http::withHeaders($this->getHeaders($category))->get($url);

        if ($response->failed()) {
            return response()->json(['status' => 'error'], 404);
        }

        $data = $response->json();
        $formattedData = $this->formatResponse($category, $data);

        return response()->json(['data' => $formattedData]);
    }

    private function getUrl(string $category, string|int $pluginId): string
    {
        return match ($category) {
            'spigotmc' => "https://api.spiget.org/v2/resources/{$pluginId}/versions?sort=-releaseDate",
            'curseforge' => "https://api.curseforge.com/v1/mods/{$pluginId}/files",
            'hangar' => "https://hangar.papermc.io/api/v1/projects/{$pluginId}/versions",
            'polymart' => "https://api.polymart.org/v1/getResourceUpdates/&resource_id={$pluginId}",
            'modrinth' => "https://api.modrinth.com/v2/project/{$pluginId}/version",
        };
    }

    private function getHeaders(string $category): array
    {
        $apiKey = $this->blueprint->dbGet('mcplugins', 'curseforge_api_key', null); 

        return match ($category) {
            'modrinth' => [],
            'curseforge' => [
                'Accept' => 'application/json',
                'x-api-key' => $apiKey,
            ],
            'hangar' => [],
            'polymart' => [],
            'spigotmc' => [],
        };
    }

    private function formatResponse(string $category, array $data): array
    {
        return match ($category) {

            'modrinth' => array_map(fn($version) => [
                'category' => $category,
                'versionId' => $version['id'],
                'versionName' => $version['name'],
                'downloads' => $version['downloads'] ?? 0,
                'downloadUrl' => null,
            ], $data),
            
            'curseforge' => array_map(fn($version) => [
                'category' => $category,
                'versionId' => $version['id'],
                'versionName' => $version['displayName'],
                'downloads' => $version['downloadCount'] > 0 ? $version['downloadCount'] : null,
                'downloadUrl' => null,
            ], $data['data']),
            
            'hangar' => $this->formatHangarResponse($data['result'], $category),

            'spigotmc' => array_map(fn($version) => [
                'category' => $category,
                'versionId' => $version['id'],
                'versionName' => $version['name'],
                'downloads' => $version['downloads'],
                'downloadUrl' => "https://www.spigotmc.org/resources/{$version['resource']}/download?version={$version['id']}",
            ], $data),

            'polymart' => array_map(fn($version) => [
                'category' => $category,
                'versionId' => $version['id'],
                'versionName' => $version['version'],
                'downloadUrl' => $version['url'],
            ], $data['response']['updates']),
        };
    }

    private function formatHangarResponse(array $versions, string $category): array
    {
        $uniqueVersions = [];
        foreach ($versions as $version) {
            $platformDownloads = [
                'PAPER' => isset($version['stats']['platformDownloads']['PAPER']) ? $version['stats']['platformDownloads']['PAPER'] : 0,
                'WATERFALL' => isset($version['stats']['platformDownloads']['WATERFALL']) ? $version['stats']['platformDownloads']['WATERFALL'] : 0,
                'VELOCITY' => isset($version['stats']['platformDownloads']['VELOCITY']) ? $version['stats']['platformDownloads']['VELOCITY'] : 0,
            ];
            foreach ($platformDownloads as $platform => $downloads) {
                if ($downloads > 0) {
                    $versionKey = $version['name'] . ' - ' . $platform;
                    if (!isset($uniqueVersions[$versionKey])) {
                        $uniqueVersions[$versionKey] = [
                            'category' => $category,
                            'versionId' => $versionKey,
                            'versionName' => $version['name'] . ' - ' . $platform,
                            'downloads' => $downloads,
                            'downloadUrl' => null,
                        ];
                    }
                }
            }
        }
        return array_values($uniqueVersions);
    }
}
