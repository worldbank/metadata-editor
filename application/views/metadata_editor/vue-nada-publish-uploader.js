/**
 * Server-side resumable upload to NADA via ME proxy (/api/publish/nada_upload_*).
 * Files stay on the ME server; the browser only orchestrates chunk requests.
 */
(function (global) {
    'use strict';

    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function buildChunkQueryParams(ctx) {
        var params = {
            project_id: ctx.projectId,
            source: ctx.source,
            total_chunks: ctx.totalChunks
        };
        if (ctx.resourceId != null) {
            params.resource_id = ctx.resourceId;
        }
        if (ctx.serverFileKey) {
            params.server_file_key = ctx.serverFileKey;
        }
        return params;
    }

    function releaseServerFile(projectId, serverFileKey) {
        if (!serverFileKey) {
            return Promise.resolve();
        }
        return axios.post(CI.site_url + '/api/publish/nada_upload_release', {
            project_id: projectId,
            server_file_key: serverFileKey
        }).catch(function () {
            // Best-effort cleanup.
        });
    }

    /**
     * @param {object} options
     * @param {string|number} options.catalogConnectionId
     * @param {string|number} options.projectId
     * @param {string} options.source indicator_data|external_resource
     * @param {string|number} [options.resourceId] Required for external_resource
     * @param {function({progress:number,uploaded_chunks:number,total_chunks:number,status:string})} [options.onProgress]
     * @returns {Promise<{upload_id:string}>}
     */
    function uploadServerSourceToNada(options) {
        options = options || {};
        var catalogConnectionId = options.catalogConnectionId;
        var projectId = options.projectId;
        var source = options.source;
        var resourceId = options.resourceId;
        var maxRetries = options.maxRetries != null ? options.maxRetries : 3;
        var retryDelay = options.retryDelay != null ? options.retryDelay : 1000;

        if (!catalogConnectionId) {
            return Promise.reject(new Error('catalogConnectionId is required'));
        }
        if (!projectId) {
            return Promise.reject(new Error('projectId is required'));
        }
        if (!source) {
            return Promise.reject(new Error('source is required'));
        }
        if (source === 'external_resource' && (resourceId === undefined || resourceId === null || resourceId === '')) {
            return Promise.reject(new Error('resourceId is required for external_resource uploads'));
        }

        var initPayload = {
            project_id: projectId,
            source: source
        };
        if (source === 'external_resource') {
            initPayload.resource_id = resourceId;
        }

        return axios
            .post(
                CI.site_url + '/api/publish/nada_upload_init/' + encodeURIComponent(catalogConnectionId),
                initPayload,
                { timeout: 600000 }
            )
            .then(function (response) {
                var data = response.data || {};
                if (data.status !== 'success' || !data.upload_id) {
                    throw new Error((data.message) ? data.message : 'Failed to initialize NADA upload');
                }
                return {
                    projectId: projectId,
                    catalogConnectionId: catalogConnectionId,
                    source: source,
                    resourceId: data.resource_id != null ? data.resource_id : resourceId,
                    serverFileKey: data.server_file_key || null,
                    uploadId: data.upload_id,
                    chunkSize: data.chunk_size,
                    totalChunks: data.total_chunks
                };
            })
            .then(function (ctx) {
                var chunkBaseUrl = CI.site_url + '/api/publish/nada_upload_chunk/'
                    + encodeURIComponent(ctx.catalogConnectionId) + '/'
                    + encodeURIComponent(ctx.uploadId);

                function emitProgress(progressData) {
                    if (options.onProgress) {
                        options.onProgress({
                            progress: progressData.progress,
                            uploaded_chunks: progressData.uploaded_chunks,
                            total_chunks: progressData.total_chunks,
                            status: progressData.status || 'uploading'
                        });
                    }
                }

                function uploadOneChunk(chunkNumber) {
                    var queryParams = buildChunkQueryParams(ctx);

                    function attempt(retryIndex) {
                        return axios.post(chunkBaseUrl, new Uint8Array(0), {
                            params: queryParams,
                            headers: {
                                'Content-Type': 'application/octet-stream',
                                'X-Upload-Chunk-Number': chunkNumber,
                                'X-Upload-Chunk-Size': ctx.chunkSize
                            },
                            timeout: 120000
                        }).then(function (response) {
                            var data = response.data || {};
                            if (data.status !== 'success') {
                                throw new Error((data.message) ? data.message : 'NADA chunk upload failed');
                            }
                            emitProgress({
                                progress: data.progress != null
                                    ? data.progress
                                    : Math.round(((chunkNumber + 1) / ctx.totalChunks) * 100),
                                uploaded_chunks: data.uploaded_chunks != null ? data.uploaded_chunks : (chunkNumber + 1),
                                total_chunks: data.total_chunks != null ? data.total_chunks : ctx.totalChunks,
                                status: 'uploading'
                            });
                            return data;
                        }).catch(function (error) {
                            if (retryIndex >= maxRetries) {
                                var msg = (error.response && error.response.data && error.response.data.message)
                                    || error.message
                                    || 'NADA chunk upload failed';
                                return Promise.reject(new Error(msg));
                            }
                            return delay(retryDelay * Math.pow(2, retryIndex)).then(function () {
                                return attempt(retryIndex + 1);
                            });
                        });
                    }

                    return attempt(0);
                }

                var chain = Promise.resolve();
                for (var c = 0; c < ctx.totalChunks; c++) {
                    (function (chunkNumber) {
                        chain = chain.then(function () {
                            return uploadOneChunk(chunkNumber);
                        });
                    })(c);
                }

                return chain.then(function () {
                    return axios.get(
                        CI.site_url + '/api/publish/nada_upload_status/'
                            + encodeURIComponent(ctx.catalogConnectionId) + '/'
                            + encodeURIComponent(ctx.uploadId),
                        { params: { project_id: ctx.projectId }, timeout: 120000 }
                    ).then(function (response) {
                        var data = response.data || {};
                        if (data.status !== 'success') {
                            throw new Error((data.message) ? data.message : 'Failed to verify NADA upload status');
                        }
                        if (data.upload_status !== 'completed') {
                            throw new Error('NADA upload did not complete (status: ' + (data.upload_status || 'unknown') + ')');
                        }
                        emitProgress({
                            progress: 100,
                            uploaded_chunks: ctx.totalChunks,
                            total_chunks: ctx.totalChunks,
                            status: 'completed'
                        });
                        return {
                            upload_id: ctx.uploadId,
                            server_file_key: ctx.serverFileKey
                        };
                    });
                }).finally(function () {
                    return releaseServerFile(ctx.projectId, ctx.serverFileKey);
                });
            });
    }

    global.NadaPublishUploader = {
        uploadServerSourceToNada: uploadServerSourceToNada
    };
})(typeof window !== 'undefined' ? window : this);
