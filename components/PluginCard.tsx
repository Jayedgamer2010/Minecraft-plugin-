import tw from 'twin.macro';
import React, { useState, useMemo } from 'react';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import Modal from '@/components/elements/Modal';
import { Dialog } from '@/components/elements/dialog';
import { useStoreActions, Actions } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Plugin, installPlugin } from '@/blueprint/extensions/{identifier}/getPlugins';
import { faList, faCloudDownloadAlt, faDownload } from '@fortawesome/free-solid-svg-icons';
import PluginsVersionContainer from '@/blueprint/extensions/{identifier}/PluginsVersionContainer';

const PluginCard: React.FC<{ plugin: Plugin }> = ({ plugin }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);
    const [expanded, setExpanded] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const [externalDownload, setExternalDownload] = useState(false);
    const [externalUrl, setExternalUrl] = useState(false);

    const handleInstallPlugin = async (category: string, pluginId: number | string, pluginName: string) => {
        clearFlashes('mcplugins:install');
        try {
            await installPlugin(uuid, category, pluginId);
            addFlash({
                type: 'success',
                key: 'mcplugins:install',
                message: `The plugin '${pluginName}' has been successfully installed in your Plugins folder.`,
            });
        } catch (error) {
            addFlash({
                type: 'error',
                key: 'mcplugins:install',
                title: 'Error',
                message: `We were not able to install the plugin '${pluginName}'. However, you can still download this plugin from its official website.`,
            });
        }
    };

    const pluginCategory = (() => {
        if (plugin.category === 'modrinth') return 'Modrinth';
        if (plugin.category === 'curseforge') return 'CurseForge';
        if (plugin.category === 'hangar') return 'Hangar';
        if (plugin.category === 'spigotmc') return 'SpigotMC';
        if (plugin.category === 'polymart') return 'Polymart';
        return plugin.category;
    })();

    const cardStyle = useMemo(() => {
        const color = getComputedStyle(document.documentElement).getPropertyValue('--color-gray-700')
            ? `rgb(var(--color-gray-700) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageSecondary')
                ? 'var(--pageSecondary)'
                : 'hsla(209, 18%, 30%, 1)';
        return { boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)', backgroundColor: color };
    }, []);

    const buttonStyle = useMemo(() => {
        const color = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-700')
            ? `rgb(var(--color-blue-700) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageButtonDefault')
                ? 'var(--pageButtonDefault)'
                : 'rgba(37, 99, 235)';
        const hoverColor = getComputedStyle(document.documentElement).getPropertyValue('--color-blue-600')
            ? `rgb(var(--color-blue-600) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageButtonHover')
                ? 'var(--pageButtonHover)'
                : 'rgba(59, 130, 246)';
        return { backgroundColor: color, ':hover': { backgroundColor: hoverColor }, borderRadius: 'var(--borderRadius, 0.25rem)' };
    }, []);

    const secondarybuttonStyle = useMemo(() => {
        const color = getComputedStyle(document.documentElement).getPropertyValue('--color-gray-600')
            ? `rgb(var(--color-gray-600) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageSecondaryActive')
                ? 'var(--pageSecondaryActive)'
                : 'hsla(209, 14%, 37%)';
        const hoverColor = getComputedStyle(document.documentElement).getPropertyValue('--color-gray-500')
            ? `rgb(var(--color-gray-500) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageSecondaryHover')
                ? 'var(--pageSecondaryHover)'
                : 'hsla(211, 12%, 43%)';
        return { borderRadius: 'var(--borderRadius, 0.25rem)', backgroundColor: color, ':hover': { backgroundColor: hoverColor }, };
    }, []);

    const backgroundStyle = useMemo(() => {
        const color = getComputedStyle(document.documentElement).getPropertyValue('--color-gray-600')
            ? `rgb(var(--color-gray-600) / var(--tw-bg-opacity, 1))`
            : getComputedStyle(document.documentElement).getPropertyValue('--pageSecondaryActive')
                ? 'var(--pageSecondaryActive)'
                : 'hsla(209, 14%, 37%)';
        return { backgroundColor: color };
    }, []);

    const textStyle = {
        color: 'var(--pagePrimary, #fff)',
    };

    return (
        <div css={tw`rounded-lg p-4 flex flex-col h-full`} style={cardStyle}>
            <div css={tw`flex items-start mb-2`}>
                <div css={[tw`w-12 h-12 mr-4 rounded bg-neutral-600 flex items-center justify-center overflow-hidden flex-shrink-0`, backgroundStyle]}>
                    <img src={plugin.icon} alt={plugin.name} css={tw`w-10 h-10 object-cover`} />
                </div>
                <div css={tw`flex-grow`}>
                    <h3 css={tw`text-lg font-normal`} style={textStyle}>{plugin.name}</h3>
                    <p css={tw`text-sm text-neutral-400 flex items-center`}>
                        <span css={tw`mr-2`}>{pluginCategory}</span>
                        <FontAwesomeIcon icon={faDownload} css={tw`w-3 h-3 mr-1`} style={{ fill: 'var(--pagePrimaryHover, #7a98ff)' }} />
                        <span>{plugin.downloads.toLocaleString()}</span>
                    </p>
                </div>
            </div>
            <div css={tw`text-sm mb-4 text-neutral-300 flex-grow`} style={textStyle}>
                <p css={[tw`flex-grow`, expanded ? tw`` : tw`line-clamp-2`]}>{plugin.description}</p>
                {plugin.description.length > 100 && (
                    <button
                        onClick={() => setExpanded(!expanded)}
                        css={tw`text-neutral-400 hover:text-neutral-300 mt-1 text-xs`}
                    >
                        {expanded ? 'Read less' : 'Read more'}
                    </button>
                )}
            </div>
            <div css={tw`flex justify-between items-center mt-auto`}>
                <div css={tw`flex items-center space-x-2`}>
                    <button
                        onClick={() => setExternalUrl(true)}
                        css={[tw`bg-neutral-600 hover:bg-neutral-500 text-white p-2.5 rounded`, secondarybuttonStyle]}
                    >
                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512' css={tw`w-4 h-4 fill-current`}>
                            <path d='M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l82.7 0L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3l0 82.7c0 17.7 14.3 32 32 32s32-14.3 32-32l0-160c0-17.7-14.3-32-32-32L320 0zM80 32C35.8 32 0 67.8 0 112L0 432c0 44.2 35.8 80 80 80l320 0c44.2 0 80-35.8 80-80l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 112c0 8.8-7.2 16-16 16L80 448c-8.8 0-16-7.2-16-16l0-320c0-8.8 7.2-16 16-16l112 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L80 32z' />
                        </svg>
                    </button>
                </div>
                <div css={tw`flex items-center space-x-2`}>
                    <button
                        css={[tw`bg-neutral-600 hover:bg-neutral-500 text-white px-4 py-2 rounded text-sm flex items-center`, secondarybuttonStyle]}
                        onClick={() => setModalVisible(true)}
                    >
                        <FontAwesomeIcon icon={faList} css={tw`mr-2`} />
                        Versions
                    </button>
                    {plugin.installable ? (
                        <button
                            css={[tw`text-white px-4 py-2 rounded text-sm flex items-center`, buttonStyle]}
                            onClick={() => handleInstallPlugin(plugin.category, plugin.id, plugin.name)}
                        >
                            <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                            Install
                        </button>
                    ) : (
                        <button
                            onClick={() => setExternalDownload(true)}
                            css={[tw`text-white px-4 py-2 rounded text-sm flex items-center`, buttonStyle]}
                        >
                            <FontAwesomeIcon icon={faCloudDownloadAlt} css={tw`mr-2`} />
                            Install
                        </button>
                    )}
                </div>
            </div>
            <Dialog.Confirm
                open={externalUrl}
                onClose={() => setExternalUrl(false)}
                title={`Redirect To Plugin's Website`}
                confirm={'Open'}
                onConfirmed={() => {
                    window.open(plugin.pluginUrl!);
                    setExternalUrl(false);
                }}
            >
                Click 'Open' to visit the plugin's official website in a new tab.
            </Dialog.Confirm>
            <Dialog.Confirm
                open={externalDownload}
                onClose={() => setExternalDownload(false)}
                title={`Download Plugin`}
                confirm={'Open'}
                onConfirmed={() => {
                    window.open(plugin.pluginUrl!);
                    setExternalDownload(false);
                }}
            >
                This plugin is only available for download on its official website. Click 'Open' to download.
            </Dialog.Confirm>
            <Modal visible={modalVisible} onDismissed={() => setModalVisible(false)} closeOnBackground={false}>
                <PluginsVersionContainer
                    category={plugin.category}
                    pluginId={plugin.id}
                    pluginName={plugin.name}
                    pluginUrl={plugin.pluginUrl}
                />
            </Modal>
        </div>
    );
};

export default PluginCard;