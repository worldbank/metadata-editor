/**
 * Shared resumable chunk upload (init + sequential chunks).
 * Does not finalize to /api/files — caller registers the file (e.g. POST /api/data/datafile).
 */
(function (global) {
    'use strict';

    var limitsCache = null;

    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function fetchLimits() {
        if (limitsCache) {
            return Promise.resolve(limitsCache);
        }
        return axios
            .get(CI.base_url + '/api/uploads/limits')
            .then(function (response) {
                if (response.data && response.data.status === 'success') {
                    limitsCache = {
                        recommended_chunk_size: response.data.recommended_chunk_size || 10485760,
                        max_chunk_size: response.data.max_chunk_size || 10485760
                    };
                } else {
                    limitsCache = {
                        recommended_chunk_size: 10485760,
                        max_chunk_size: 10485760
                    };
                }
                return limitsCache;
            })
            .catch(function () {
                limitsCache = {
                    recommended_chunk_size: 10485760,
                    max_chunk_size: 10485760
                };
                return limitsCache;
            });
    }

    function readChunkAsArrayBuffer(chunk) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () {
                resolve(reader.result);
            };
            reader.onerror = function () {
                reject({ error: 'CHUNK_READ_FAILED', message: 'Failed to read chunk data' });
            };
            reader.readAsArrayBuffer(chunk);
        });
    }

    /**
     * @param {File} file
     * @param {object} options
     * @param {string|number} options.projectId
     * @param {string} [options.fileType='data']
     * @param {number} [options.chunkSize]
     * @param {number} [options.maxChunkSize]
     * @param {number} [options.maxRetries=3]
     * @param {number} [options.retryDelay=1000]
     * @param {boolean} [options.exponentialBackoff=true]
     * @param {function(): boolean} [options.cancelRequested]
     * @param {function(string)} [options.onUploadInitialized]
     * @param {function({progress:number,uploaded_chunks:number,total_chunks:number,status:string})} [options.onProgress]
     * @param {function(boolean)} [options.onInitializing]
     * @returns {Promise<{upload_id:string, serverFilename:string|null}>}
     */
    function uploadFileChunks(file, options) {
        options = options || {};
        var projectId = options.projectId;
        var fileType = options.fileType || 'data';
        var maxRetries = options.maxRetries != null ? options.maxRetries : 3;
        var retryDelay = options.retryDelay != null ? options.retryDelay : 1000;
        var exponentialBackoff = options.exponentialBackoff !== false;
        var cancelRequested = options.cancelRequested || function () {
            return false;
        };

        return fetchLimits().then(function (limits) {
            var maxChunk = options.maxChunkSize || limits.max_chunk_size;
            var chunkSize = options.chunkSize || Math.min(limits.recommended_chunk_size, maxChunk);
            chunkSize = Math.min(chunkSize, maxChunk);
            if (chunkSize < 1) {
                chunkSize = 1048576;
            }

            var totalSize = file.size;
            var totalChunks = Math.ceil(totalSize / chunkSize) || 1;

            if (options.onInitializing) {
                options.onInitializing(true);
            }

            return axios
                .post(CI.base_url + '/api/uploads/init', {
                    filename: file.name,
                    total_size: totalSize,
                    total_chunks: totalChunks,
                    chunk_size: chunkSize,
                    metadata: {
                        project_id: projectId,
                        file_type: fileType
                    }
                })
                .then(function (response) {
                    if (options.onInitializing) {
                        options.onInitializing(false);
                    }
                    if (!response.data || response.data.status !== 'success') {
                        throw new Error((response.data && response.data.message) || 'Failed to initialize upload');
                    }
                    var uploadId = response.data.upload_id;
                    if (options.onUploadInitialized) {
                        options.onUploadInitialized(uploadId);
                    }
                    return { uploadId: uploadId, chunkSize: chunkSize, totalChunks: totalChunks };
                })
                .catch(function (error) {
                    if (options.onInitializing) {
                        options.onInitializing(false);
                    }
                    var msg =
                        (error.response && error.response.data && error.response.data.message) ||
                        error.message ||
                        'Failed to initialize upload';
                    return Promise.reject({ error: 'INIT_FAILED', message: msg });
                });
        }).then(function (ctx) {
            var uploadId = ctx.uploadId;
            var chunkSize = ctx.chunkSize;
            var totalChunks = ctx.totalChunks;
            var serverFilename = null;

            function emitProgress(uploadedChunkIndex) {
                if (options.onProgress) {
                    options.onProgress({
                        progress: Math.round(((uploadedChunkIndex + 1) / totalChunks) * 100),
                        uploaded_chunks: uploadedChunkIndex + 1,
                        total_chunks: totalChunks,
                        status: 'uploading'
                    });
                }
            }

            function uploadOneChunk(chunkNumber) {
                var maxAttempts = maxRetries + 1;

                function attempt(attemptIndex) {
                    if (cancelRequested()) {
                        return Promise.reject({ error: 'UPLOAD_CANCELLED', message: 'Upload cancelled by user' });
                    }
                    var start = chunkNumber * chunkSize;
                    var end = Math.min(start + chunkSize, file.size);
                    var blob = file.slice(start, end);

                    return readChunkAsArrayBuffer(blob).then(function (chunkData) {
                        var actualChunkSize = chunkData.byteLength;
                        return axios
                            .post(
                                CI.base_url + '/api/uploads/chunk/' + uploadId,
                                chunkData,
                                {
                                    headers: {
                                        'Content-Type': 'application/octet-stream',
                                        'X-Upload-Chunk-Number': chunkNumber,
                                        'X-Upload-Chunk-Size': actualChunkSize
                                    },
                                    timeout: 30000
                                }
                            )
                            .then(function (response) {
                                var data = response.data;
                                if (!data || data.status !== 'success') {
                                    throw new Error((data && data.message) || 'Chunk upload failed');
                                }
                                if (data.upload_status === 'complete' && data.filename) {
                                    serverFilename = data.filename;
                                }
                                return data;
                            })
                            .catch(function (error) {
                                var msg =
                                    (error.response && error.response.data && error.response.data.message) ||
                                    error.message ||
                                    'Chunk upload failed';
                                throw { error: 'CHUNK_UPLOAD_FAILED', message: msg };
                            });
                    });
                }

                function tryWithRetries(attemptIndex) {
                    return attempt(attemptIndex).catch(function (err) {
                        if (attemptIndex >= maxAttempts - 1) {
                            return Promise.reject(err);
                        }
                        var wait = exponentialBackoff ? retryDelay * Math.pow(2, attemptIndex) : retryDelay;
                        return delay(wait).then(function () {
                            return tryWithRetries(attemptIndex + 1);
                        });
                    });
                }

                return tryWithRetries(0);
            }

            var chain = Promise.resolve();
            for (var c = 0; c < totalChunks; c++) {
                (function (chunkNumber) {
                    chain = chain.then(function () {
                        return uploadOneChunk(chunkNumber).then(function () {
                            emitProgress(chunkNumber);
                        });
                    });
                })(c);
            }

            return chain.then(function () {
                return {
                    upload_id: uploadId,
                    serverFilename: serverFilename
                };
            });
        });
    }

    global.ResumableChunkUploader = {
        uploadFileChunks: uploadFileChunks,
        clearLimitsCache: function () {
            limitsCache = null;
        }
    };
})(typeof window !== 'undefined' ? window : this);
