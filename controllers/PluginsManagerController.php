<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Models\Permission;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;

class PluginsManagerController extends Controller
{
    public function __construct(
        private BlueprintAdminLibrary $blueprint
    ) {}
    
    public function index(Request $request, Server $server)
    {
        if (!$request->user()->can(Permission::ACTION_FILE_READ, $server)) {
            throw new AuthorizationException();
        }

        $category = $request->query('category', 'modrinth');
        $page = $request->query('page', 1);
        $pageSize = $request->query('page_size', 6);
        $searchQuery = $request->query('search_query', '');
        $type = $request->query('type', '');
        $sortBy = $request->query('sort_by', '');
        $minecraftVersion = $request->query('minecraft_version', '');

        $url = $this->getUrl($category, $page, $pageSize, $searchQuery, $type, $sortBy, $minecraftVersion);
        $response = Http::withHeaders($this->getHeaders($category))->get($url);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'response' => $response->body()
            ], $response->status());
        }

        $data = $response->json();
        $pagination = $this->getPagination($category, $data, $page, $pageSize);
        $formattedData = $this->formatResponse($category, $data);

        return response()->json([
            'data' => $formattedData,
            'pagination' => $pagination,
        ]);
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
            'spigotmc' => [],
            'polymart' => [],
        };
    }

    private function getUrl(string $category, int $page, int $pageSize, string $searchQuery, string $type, string $sortBy, string $minecraftVersion): string
    {
        $offset = ($page - 1) * $pageSize;
    
        return match ($category) {
            'modrinth' => $this->getModrinthUrl($pageSize, $searchQuery, $sortBy, $offset, $type, $minecraftVersion),
            'curseforge' => $this->getCurseForgeUrl($pageSize, $searchQuery, $sortBy, $offset, $type, $minecraftVersion),
            'hangar' => $this->getHangarUrl($pageSize, $offset, $searchQuery, $sortBy, $minecraftVersion),
            'spigotmc' => $this->getSpigotmcUrl($pageSize, $page, $searchQuery, $sortBy),
            'polymart' => $this->getPolymartUrl($pageSize, $page, $searchQuery, $sortBy),
        };
    }

    private function getModrinthUrl(int $pageSize, string $searchQuery, string $sortBy, int $offset, string $type, string $minecraftVersion): string
    {
        $baseUrl = "https://api.modrinth.com/v2/search";
        $facets = [
            ["categories:$type"],
            ["server_side!=unsupported"],
        ];

        if ($minecraftVersion) {
            $facets[] = ["versions:$minecraftVersion"];
        }

        $facetsQuery = urlencode(json_encode($facets));

        return "{$baseUrl}?limit={$pageSize}&query={$searchQuery}&index={$sortBy}&offset={$offset}&facets={$facetsQuery}";
    }

    private function getCurseForgeUrl(int $pageSize, string $searchQuery, string $sortBy, int $offset, string $type, string $minecraftVersion): string
    {
        $gameId = 432;
        $classId = 5;
        $sortOrder = "desc";
        $baseUrl = "https://api.curseforge.com/v1/mods/search";

        return "{$baseUrl}?gameId={$gameId}&classId={$classId}&pageSize={$pageSize}&index={$offset}&searchFilter={$searchQuery}&modLoaderType={$type}&gameVersion={$minecraftVersion}&sortField={$sortBy}&sortOrder={$sortOrder}";
    }

    private function getHangarUrl(int $pageSize, int $offset, string $searchQuery, string $sortBy, string $minecraftVersion): string
    {
        $baseUrl = "https://hangar.papermc.io/api/v1/projects";
        $params = [
            'limit' => $pageSize,
            'offset' => $offset,
            'sort' => $sortBy,
        ];
        if ($minecraftVersion) {
            $params['version'] = $minecraftVersion;
        }
        if ($searchQuery) {
            $params['query'] = $searchQuery;
        }
        $queryString = http_build_query($params);
        
        return "{$baseUrl}?{$queryString}";
    }

    private function getSpigotmcUrl(int $pageSize, int $page, string $searchQuery, string $sortBy): string
    {
        $baseUrl = $searchQuery ? "https://api.spiget.org/v2/search/resources/{$searchQuery}" : "https://api.spiget.org/v2/resources";
        return "{$baseUrl}?size={$pageSize}&page={$page}&sort={$sortBy}";
    }

    private function getPolymartUrl(int $pageSize, int $page, string $searchQuery, string $sortBy): string
    {
        $baseUrl = "https://api.polymart.org/v1/search";
        return "{$baseUrl}?limit={$pageSize}&start={$page}&query={$searchQuery}&sort={$sortBy}";
    }

    private function getPagination(string $category, array $data, int $page, int $pageSize): array
    {
        return match ($category) {
            'modrinth' => [
                'total' => (int)$data['total_hits'],
                'count' => count($data['hits']),
                'per_page' => $pageSize,
                'current_page' => $page,
                'total_pages' => (int)ceil($data['total_hits'] / $pageSize),
            ],
            'curseforge' => [
                'total' => (int)$data['pagination']['totalCount'],
                'count' => (int)$data['pagination']['resultCount'],
                'per_page' => $pageSize,
                'current_page' => $page,
                'total_pages' => (int)ceil(
                ((int)$data['pagination']['totalCount'] < 9996 
                ? (int)$data['pagination']['totalCount'] 
                : 9996) / $pageSize
                ),
            ],
            'hangar' => [
                'total' => (int)$data['pagination']['count'],
                'count' => count($data['result']),
                'per_page' => $pageSize,
                'current_page' => $page,
                'total_pages' => (int)ceil($data['pagination']['count'] / $pageSize),
            ],
            'spigotmc' => [
                'total' => (int)(count($data) < $pageSize ? count($data) : 300),
                'count' => count($data),
                'per_page' => $pageSize,
                'current_page' => $page,
                'total_pages' => (int)(count($data) < $pageSize ? 1 : 50),
            ],
            'polymart' => [
                'total' => (int)$data['response']['total'],
                'count' => (int)$data['response']['result_count'],
                'per_page' => $pageSize,
                'current_page' => $page,
                'total_pages' => (int)ceil($data['response']['total'] / $pageSize),
            ],
        };
    }

    private function formatResponse(string $category, array $data): array
    {
        return match ($category) {
            'modrinth' => $this->formatModrinthResponse($data),
            'curseforge' => $this->formatCurseForgeResponse($data),
            'hangar' => $this->formatHangarResponse($data),
            'spigotmc' => $this->formatSpigotmcResponse($data),
            'polymart' => $this->formatPolymartResponse($data),
        };
    }

    private function formatModrinthResponse(array $data): array
    {
        return array_map(function ($plugin) {
            return [
                'category' => 'modrinth',
                'id' => $plugin['project_id'],
                'name' => $plugin['title'],
                'description' => $plugin['description'],
                'icon' => $plugin['icon_url'],
                'downloads' => $plugin['downloads'],
                'pluginUrl' => "https://modrinth.com/plugin/{$plugin['project_id']}",
                'installable' => true,
            ];
        }, $data['hits']);
    }
    
    private function formatCurseForgeResponse(array $data): array
    {
        return array_map(function ($plugin) {
            return [
                'category' => 'curseforge',
                'id' => $plugin['id'],
                'name' => $plugin['name'],
                'description' => $plugin['summary'],
                'icon' => $plugin['logo']['url'] ?? null,
                'downloads' => $plugin['downloadCount'] ?? 0,
                'pluginUrl' => "https://www.curseforge.com/minecraft/bukkit-plugins/{$plugin['slug']}",
                'installable' => true,
            ];
        }, $data['data']);
    }

    private function formatHangarResponse(array $data): array
    {
        return array_map(function ($plugin) {
            return [
                'category' => 'hangar',
                'id' => $plugin['name'],
                'name' => $plugin['name'],
                'description' => $plugin['description'],
                'icon' => $plugin['avatarUrl'],
                'downloads' => $plugin['stats']['downloads'],
                'pluginUrl' => "https://hangar.papermc.io/{$plugin['namespace']['owner']}/{$plugin['name']}",
                'installable' => true,
            ];
        }, $data['result']);
    }

    private function formatSpigotmcResponse(array $data): array
    {
        return array_map(function ($plugin) {
            $installable = true;
            if (isset($plugin['file']['externalUrl']) && !str_ends_with($plugin['file']['externalUrl'], '.jar')) {
                $installable = false;
            }
            if (isset($plugin['premium']) && $plugin['premium']) {
                $installable = false;
            }
            return [
                'category' => 'spigotmc',
                'id' => $plugin['id'],
                'name' => $plugin['name'],
                'description' => $plugin['tag'],
                'icon' => "https://www.spigotmc.org/{$plugin['icon']['url']}",
                'downloads' => $plugin['downloads'],
                'pluginUrl' => "https://www.spigotmc.org/resources/{$plugin['id']}",
                'installable' => $installable,
            ];
        }, $data);
    }

    private function formatPolymartResponse(array $data): array
    {
        return array_map(function ($plugin) {
            return [
                'category' => 'polymart',
                'id' => $plugin['id'],
                'name' => $plugin['title'],
                'description' => $plugin['subtitle'],
                'icon' => $plugin['thumbnailURL'],
                'downloads' => $plugin['totalDownloads'],
                'pluginUrl' => $plugin['url'],
                'installable' => (bool)$plugin['canDownload'],
            ];
        }, $data['response']['result']);
    }
}
