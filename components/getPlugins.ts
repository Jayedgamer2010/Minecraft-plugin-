import http, { getPaginationSet, PaginatedResult } from '@/api/http';

export interface Plugin {
    category: string;
    id: number | string;
    name: string;
    description: string;
    icon: string;
    downloads: number;
    pluginUrl: string;
    installable: boolean;
}

export const rawDataToPlugin = (data: any): Plugin => {
    return {
        category: data.category,
        id: data.id,
        name: data.name,
        description: data.description,
        icon: data.icon,
        downloads: data.downloads,
        pluginUrl: data.pluginUrl,
        installable: data.installable,
    };
};

export type PluginsResponse = PaginatedResult<Plugin>;

export const getPlugins = (
    uuid: string,
    category: string,
    page: number,
    pageSize: number,
    searchQuery: string,
    type: string,
    sortBy: string,
    minecraftVersion: string
): Promise<PluginsResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/extensions/mcplugins/servers/${uuid}/mcplugins`, {
            params: {
                category: category,
                page: page,
                page_size: pageSize,
                search_query: searchQuery,
                type: type,
                sort_by: sortBy,
                minecraft_version: minecraftVersion,
            },
        })
            .then((response) => {
                const plugins = response.data.data.map((item: any) => rawDataToPlugin(item));
                const pagination = getPaginationSet(response.data.pagination);
                resolve({
                    items: plugins,
                    pagination: pagination,
                });
            })
            .catch(reject);
    });
};

export const installPlugin = (uuid: string, category: string, pluginId: string | number): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/extensions/mcplugins/servers/${uuid}/mcplugins/install`, {
            category,
            pluginId,
        })
            .then(() => resolve())
            .catch(reject);
    });
};
