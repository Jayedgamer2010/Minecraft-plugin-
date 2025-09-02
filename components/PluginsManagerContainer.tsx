import tw from 'twin.macro';
import { ServerContext } from '@/state/server';
import { PaginationDataSet } from '@/api/http';
import React, { useState, useEffect } from 'react';
import Spinner from '@/components/elements/Spinner';
import { CSSTransition } from 'react-transition-group';
import Pagination from '@/components/elements/Pagination';
import FlashMessageRender from '@/components/FlashMessageRender';
import SearchRow from '@/blueprint/extensions/{identifier}/SearchRow';
import PluginCard from '@/blueprint/extensions/{identifier}/PluginCard';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { Plugin, getPlugins } from '@/blueprint/extensions/{identifier}/getPlugins';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [category, setCategory] = useState('modrinth');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(6);
    const [searchQuery, setSearchQuery] = useState('');
    const [type, setType] = useState('paper');
    const [sortBy, setSortBy] = useState('downloads');
    const [minecraftVersion, setMinecraftVersion] = useState('');
    const [plugins, setPlugins] = useState<Plugin[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [paginationData, setPaginationData] = useState<PaginationDataSet | null>(null);

    useEffect(() => {
        let isMounted = true;
        const fetchPlugins = async () => {
            setLoading(true);
            setError(null);
            try {
                const { items, pagination } = await getPlugins(
                    uuid,
                    category,
                    page,
                    pageSize,
                    searchQuery,
                    type,
                    sortBy,
                    minecraftVersion
                );
                if (isMounted) {
                    setPlugins(items);
                    setPaginationData(pagination);
                }
            } catch (error) {
                if (isMounted) {
                    setError('Error fetching plugins');
                }
            } finally {
                if (isMounted) {
                    setLoading(false);
                }
            }
        };
        fetchPlugins();
        return () => {
            isMounted = false;
        };
    }, [uuid, category, page, pageSize, searchQuery, type, sortBy, minecraftVersion]);

    useEffect(() => {
        setPage(1);
    }, [pageSize, category]);

    const handleSearch = (query: string) => {
        setSearchQuery(query);
        setPage(1);
    };

    const handlePageSelect = (selectedPage: number) => {
        setPage(selectedPage);
    };

    return (
        <ServerContentBlock title={'Plugin Manager'}>
            <SearchRow
                onSearch={handleSearch}
                minecraftVersion={minecraftVersion}
                setMinecraftVersion={setMinecraftVersion}
                category={category}
                setCategory={setCategory}
                sortBy={sortBy}
                setSortBy={setSortBy}
                type={type}
                setType={setType}
                pageSize={pageSize}
                setPageSize={setPageSize}
            />
            <FlashMessageRender byKey={'mcplugins:install'} css={tw`mt-6`} />
            {loading ? (
                <div css={tw`w-full flex justify-center mt-6`}>
                    <Spinner size='large' />
                </div>
            ) : error || plugins.length === 0 ? (
                <div css={tw`mt-6`}>No plugins were found.</div>
            ) : (
                <CSSTransition classNames={'fade'} timeout={150} appear in>
                    <Pagination data={{ items: plugins, pagination: paginationData! }} onPageSelect={handlePageSelect}>
                        {({ items }) => (
                            <div>
                                <div css={tw`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6`}>
                                    {items.map((plugin) => (
                                        <PluginCard key={plugin.id} plugin={plugin} />
                                    ))}
                                </div>
                                <div css={tw`w-full flex justify-center my-4 text-sm`}>
                                    {category !== 'spigotmc' && (
                                        <div>{`Showing ${plugins.length} out of ${paginationData?.total || 0} Plugins`}</div>
                                    )}
                                </div>
                            </div>
                        )}
                    </Pagination>
                </CSSTransition>
            )}
        </ServerContentBlock>
    );
};
