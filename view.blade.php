<div class="row">
  <div class="col-md-6">
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title">Information</h3>
      </div>
      <div class="box-body">
        <p>
          {name} Installer was made with 
          <i class="fa fa-heart" style="color: #FB0000"></i> 
          by StellarStudios.
        </p>
        <p>
          Need help? Join us on 
          <a href="https://discord.gg/sQjuWcDxBY" target="_blank">Discord</a> 
          for support.
        </p>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <tbody>
            <tr>
              <td>Identifier</td>
              <td><code>{identifier}</code></td>
            </tr>
            <tr>
              <td>Version</td>
              <td><code>v{version}</code></td>
            </tr>
            <tr>
              <td>Author</td>
              <td><code>{author}</code></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="box box-success">
      <div class="box-header with-border">
        <h3 class="box-title">Configuration</h3>
      </div>
      <div class="box-body">
        <p>
          Configure which Pterodactyl Eggs {name} should work on.
        </p>
        <button class="btn btn-gray-alt" 
                style="padding: 5px 10px; margin-top: 10px;" 
                data-toggle="modal" 
                data-target="#extensionConfigModal">
          Configure
        </button>
      </div>
    </div>

<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-key"></i> API Key Configuration</h3>
    </div>
    <form action="{{ route('admin.extensions.mcplugins.patch') }}" method="POST">
        @csrf
        @method('PATCH')
        <div class="box-body">
            <div class="form-group">
                <label for="curseforge_api_key">CurseForge API Key</label>
                <input type="text" class="form-control" id="curseforge_api_key" name="curseforge_api_key" value="{{ old('curseforge_api_key', $curseForgeApiKey) }}" required placeholder="Enter your CurseForge API key">
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update API Key</button>
        </div>
    </form>
</div>
  </div>
</div>
