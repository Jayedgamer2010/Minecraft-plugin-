<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;

class {identifier}ExtensionController extends Controller
{
    protected BlueprintAdminLibrary $blueprint;

    public function __construct(BlueprintAdminLibrary $blueprint)
    {
        $this->blueprint = $blueprint;
    }

    public function index(): View
    {
        $curseForgeApiKey = $this->blueprint->dbGet('{identifier}', 'curseforge_api_key', '');

        return view('admin.extensions.{identifier}.index', [
            'curseForgeApiKey' => $curseForgeApiKey,
            'blueprint' => $this->blueprint,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'curseforge_api_key' => 'required|string|max:255',
        ]);

        $this->blueprint->dbSet('{identifier}', 'curseforge_api_key', $request->input('curseforge_api_key'));

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', 'CurseForge API Key has been updated successfully!');
    }
}
