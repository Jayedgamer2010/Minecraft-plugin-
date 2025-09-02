import http from '@/api/http';

export interface Version {
    versionId: string | number;
    versionName: string;
    downloads?: number;
    downloadUrl?: string;
}

export const rawDataToVersion = (data: any): Version => {
    return {
        versionId: data.versionId,
        versionName: data.versionName,
        downloads: data.downloads,
        downloadUrl: data.downloadUrl,
    };
};

export const getPluginVersions = (uuid: string, category: string, pluginId: string | number): Promise<Version[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/extensions/mcplugins/servers/${uuid}/mcplugins/version`, {
            params: {
                category,
                pluginId,
            },
        })
            .then((response) => {
                resolve(response.data.data.map((item: any) => rawDataToVersion(item)));
            })
            .catch(reject);
    });
};

export const installPluginVersion = (
    uuid: string,
    category: string,
    pluginId: string | number,
    versionId: string | number
): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/extensions/mcplugins/servers/${uuid}/mcplugins/install`, {
            category,
            pluginId,
            versionId,
        })
            .then(() => resolve())
            .catch(reject);
    });
};
