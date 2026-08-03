(function() {
  const translationsJsonBase64 = '<?php echo base64_encode(json_encode(isset($translations) ? $translations : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>';
  const translation_messages = {
    default: JSON.parse(atob(translationsJsonBase64))
  };

  const i18n = new VueI18n({
    locale: 'default',
    messages: translation_messages
  });

  const vuetify = new Vuetify({
    theme: {
      themes: {
        light: {
          primary: '#526bc7',
          'primary-dark': '#0c1a4d',
          secondary: '#b0bec5',
          accent: '#8c9eff',
          error: '#b71c1c'
        }
      }
    }
  });

  const siteUrl = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url : '';
  const baseApiRoot = siteUrl.replace(/\/?$/, '/');

  function createDefaultForm() {
    return {
      uid: '',
      title: '',
      description: '',
      metadata_options: {
        core_fields: {
          idno: [],
          title: [],
          country: [],
          year_start: [],
          year_end: [],
          attributes: {}
        }
      }
    };
  }

  const SchemaList = {
    template: '#schema-list-template',
    data() {
      return {
        schemas: [],
        loading: false
      };
    },
    computed: {
      baseApiUrl() {
        return baseApiRoot + 'api/schemas';
      },
      headers() {
        return [
          { text: this.$t('schema_icon'), value: 'icon_url', sortable: false, align: 'center', width: '32px' },
          { text: this.$t('title'), value: 'title' },
          { text: this.$t('schema'), value: 'uid', sortable: true },
          { text: this.$t('alias'), value: 'alias', sortable: true },
          { text: this.$t('status'), value: 'status' },
          { text: this.$t('type'), value: 'is_core' },
          { text: this.$t('updated'), value: 'updated' },
          { text: '', value: 'actions', align: 'end', sortable: false }
        ];
      }
    },
    created() {
      this.loadSchemas();
    },
    methods: {
      iconSrc(item) {
        if (!item) {
          return null;
        }

        const normalizeBaseUrl = (value) => {
          if (!value) {
            return '';
          }
          return value.endsWith('/') ? value : value + '/';
        };

        const isAbsoluteUrl = (value) => /^https?:\/\//i.test(value) || /^\/\//.test(value);

        const baseUrl = normalizeBaseUrl((typeof CI !== 'undefined' && CI.base_url) ? CI.base_url : siteUrl.replace(/\/?$/, '/'));

        if (item.icon_full_url && typeof item.icon_full_url === 'string') {
          const trimmedFull = item.icon_full_url.trim();
          if (trimmedFull && !trimmedFull.endsWith('/')) {
            return trimmedFull;
          }
        }

        if (item.icon_url) {
          const iconUrl = item.icon_url;
          if (isAbsoluteUrl(iconUrl)) {
            return iconUrl;
          }

          const cleanedIcon = iconUrl.replace(/^\/+/, '');
          if (cleanedIcon) {
            return baseUrl + cleanedIcon;
          }
        }

        return null;
      },
      loadSchemas() {
        this.loading = true;

        axios.get(this.baseApiUrl)
          .then(response => {
            if (response.data && response.data.schemas) {
              this.schemas = response.data.schemas;
            } else {
              this.schemas = [];
            }
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : 'Failed to load schemas';
            this.$alert(message, { color: 'error' });
          })
        .finally(() => {
          this.loading = false;
        });
      },
      formatDate(value) {
        if (!value) {
          return '';
        }

        let momentValue;

        if (typeof value === 'number' || (typeof value === 'string' && /^\d+$/.test(value))) {
          momentValue = moment.unix(parseInt(value, 10));
        } else {
          momentValue = moment(value);
        }

        if (!momentValue.isValid()) {
          return value;
        }

        return momentValue.utc().format('YYYY-MM-DD HH:mm');
      },
      isCoreSchema(item) {
        return !!(item && Number(item.is_core) === 1);
      },
      editSchema(item) {
        if (!item || this.isCoreSchema(item)) {
          return;
        }
        this.$router.push({
          name: 'edit-schema',
          params: { uid: item.uid }
        });
      },
      editSchemaMappings(item) {
        if (!item) {
          return;
        }
        this.$router.push({
          name: 'schema-mappings',
          params: { uid: item.uid }
        });
      },
      deleteSchema(item) {
        if (!item || this.isCoreSchema(item)) {
          return;
        }
        const title = item.title || item.uid;
        this.$confirm(this.$t('delete_schema_confirm', { title: title }))
          .then(() => {
            axios.delete(this.baseApiUrl + '/' + encodeURIComponent(item.uid))
              .then(() => {
                this.$alert(this.$t('schema_deleted'), { color: 'success' });
                this.loadSchemas();
              })
              .catch(error => {
                const data = error.response && error.response.data;
                let message = 'Failed to delete schema';
                if (data && data.error_code === 'schema_in_use_by_projects') {
                  message = this.$t('schema_delete_in_use_by_projects');
                } else if (data && data.message) {
                  message = data.message;
                }
                this.$alert(message, { color: 'error' });
              });
          })
          .catch(() => {});
      },
      regenerateTemplate(item) {
        if (!item || this.isCoreSchema(item)) {
          return;
        }

        this.$confirm(this.$t('regenerate_template_confirm'))
          .then(() => {
            axios.post(this.baseApiUrl + '/regenerate_template/' + encodeURIComponent(item.uid))
              .then(() => {
                this.$alert(this.$t('schema_template_regenerated'), { color: 'success' });
                this.loadSchemas();
              })
              .catch(error => {
                const message = (error.response && error.response.data && error.response.data.message)
                  ? error.response.data.message
                  : this.$t('regenerate_template_failed');
                this.$alert(message, { color: 'error' });
              });
          })
          .catch(() => {});
      },
      previewSchema(item) {
        if (!item || !item.uid) {
          return;
        }
        const previewUrl = siteUrl.replace(/\/?$/, '/') + 'schemas/preview/' + encodeURIComponent(item.uid);
        window.open(previewUrl, '_blank', 'noopener');
      },
      handleTitleClick(item) {
        if (!item || this.isCoreSchema(item)) {
          return;
        }
        this.editSchema(item);
      },
    }
  };

  const SchemaForm = {
    template: '#schema-create-template',
    props: {
      mode: {
        type: String,
        default: 'create'
      },
      schemaUid: {
        type: String,
        default: ''
      }
    },
    data() {
      return {
        form: createDefaultForm(),
        uploading: false,
        valid: false,
        mainFile: null,
        associatedFiles: [],
        rules: {
          required: value => !!value || this.$t('required'),
          uid: value => /^[a-zA-Z0-9_-]{3,64}$/.test(value) || this.$t('invalid_uid')
        },
        initializing: false,
        currentSchema: null,
        fileManifest: [],
        fileManifestLoading: false,
        deletingFiles: {},
        pendingUploadLoading: false,
        formErrorMessage: '',
        uploadErrorMessage: '',
        reservedRootProperties: []
      };
    },
    computed: {
      baseApiUrl() {
        return baseApiRoot + 'api/schemas';
      },
      isCreate() {
        return this.mode !== 'edit';
      },
      formTitle() {
        return this.isCreate ? this.$t('create_schema') : this.$t('edit_schema');
      },
      submitButtonText() {
        return this.$t('save');
      },
      uidFieldRules() {
        return this.isCreate ? [this.rules.required, this.rules.uid] : [];
      },
      isSaveDisabled() {
        if (this.uploading || this.pendingUploadLoading) {
          return true;
        }

        if (this.isCreate) {
          return !this.mainFile;
        }

        return false;
      },
      existingMainFile() {
        if (!this.currentSchema || !this.currentSchema.filename) {
          return null;
        }

        const mainFilename = this.currentSchema.filename;
        let entry = null;

        if (Array.isArray(this.fileManifest) && this.fileManifest.length) {
          entry = this.fileManifest.find(file =>
            file && file.filename === mainFilename
          );
        }

        const schemaUid = this.currentSchema.uid || this.schemaUid || this.form.uid;
        const downloadUrl = entry && entry.download_url
          ? entry.download_url
          : (schemaUid
            ? siteUrl.replace(/\/?$/, '/') + 'api/schemas/file/' + encodeURIComponent(schemaUid) + '/' + encodeURIComponent(mainFilename)
            : null);

        return {
          key: 'existing-' + mainFilename,
          name: mainFilename,
          size: entry ? entry.size : null,
          download_url: downloadUrl,
          manifest: entry || null
        };
      },
      mainFileRows() {
        const rows = [];
        const existing = this.existingMainFile;

        if (existing) {
          rows.push({
            key: existing.key,
            type: 'existing',
            name: existing.name,
            size: existing.size,
            download_url: existing.download_url,
            manifest: existing.manifest || null
          });
        }

        if (this.mainFile) {
          rows.push({
            key: 'staged-' + this.mainFile.id,
            type: 'staged',
            name: this.mainFile.name,
            size: this.mainFile.size
          });
        }

        return rows;
      },
      existingAssociatedFiles() {
        if (!Array.isArray(this.fileManifest) || !this.fileManifest.length) {
          return [];
        }

        return this.fileManifest
          .filter(file => file && !file.is_main)
          .map(file => ({
            key: 'existing-associated-' + file.filename,
            name: file.filename,
            size: file.size,
            download_url: file.download_url || null,
            manifest: file
          }));
      },
      associatedFileRows() {
        const rows = [];

        this.existingAssociatedFiles.forEach(item => {
          rows.push({
            key: item.key,
            type: 'existing',
            name: item.name,
            size: item.size,
            download_url: item.download_url,
            manifest: item.manifest
          });
        });

        (this.associatedFiles || []).forEach(file => {
          rows.push({
            key: 'staged-associated-' + file.id,
            type: 'staged',
            name: file.name,
            size: file.size,
            staged: file
          });
        });

        return rows;
      },
      debugState() {
        const snapshot = {
          isCreate: this.isCreate,
          form: this.form,
          currentSchema: this.currentSchema,
          mainFile: this.mainFile,
          associatedFiles: this.associatedFiles,
          fileManifest: this.fileManifest,
          computed: {
            existingMainFile: this.existingMainFile,
            mainFileRows: this.mainFileRows,
            existingAssociatedFiles: this.existingAssociatedFiles,
            associatedFileRows: this.associatedFileRows
          }
        };

        try {
          return JSON.stringify(snapshot, null, 2);
        } catch (error) {
          return 'Unable to stringify debug state: ' + (error && error.message ? error.message : error);
        }
      }
    },
    watch: {
      schemaUid(newVal, oldVal) {
        if (!this.isCreate && newVal !== oldVal) {
          this.initializeForm();
        }
      },
      mode() {
        this.initializeForm();
      },
      'form.uid'(val) {
        if (!this.isCreate) {
          return;
        }
        if (typeof val !== 'string') {
          return;
        }
        const trimmed = val.trim();
        const sanitized = trimmed.replace(/[^a-zA-Z0-9_-]/g, '');
        if (sanitized !== this.form.uid) {
          this.form.uid = sanitized;
        }
      }
    },
    created() {
      this.initializeForm();
    },
    methods: {
      defaultForm() {
        return createDefaultForm();
      },
      initializeForm() {
        this.valid = false;
        this.uploading = false;
        this.formErrorMessage = '';
        this.uploadErrorMessage = '';
        const resetValidation = () => {
          if (this.$refs.form && typeof this.$refs.form.resetValidation === 'function') {
            this.$refs.form.resetValidation();
          }
        };
        this.$nextTick(resetValidation);
        this.mainFile = null;
        this.associatedFiles = [];
        this.form = this.defaultForm();
        this.fileManifest = [];
        this.fileManifestLoading = false;
        this.deletingFiles = {};
        this.pendingUploadLoading = false;
        this.reservedRootProperties = [];
        if (this.isCreate) {
          this.initializing = false;
          this.currentSchema = null;
          return;
        }

        if (!this.schemaUid) {
          this.$router.push({ name: 'schemas-list' });
          return;
        }

        this.initializing = true;
        this.loadSchema();
      },
      normalizeMetadataOptions(options) {
        const defaults = {
          core_fields: {
            idno: [],
            title: [],
            country: [],
            year_start: [],
            year_end: [],
            attributes: {}
          }
        };

        if (!options || typeof options !== 'object') {
          return JSON.parse(JSON.stringify(defaults));
        }

        const normalized = Object.assign({}, defaults, options);
        if (!normalized.core_fields || typeof normalized.core_fields !== 'object') {
          normalized.core_fields = Object.assign({}, defaults.core_fields);
        } else {
          // Normalize simple array fields (idno, title, country, year_start, year_end)
          normalized.core_fields = Object.assign({}, defaults.core_fields, normalized.core_fields);
          ['idno', 'title', 'country', 'year_start', 'year_end'].forEach(field => {
            const value = normalized.core_fields[field];
            if (Array.isArray(value)) {
              // Filter out empty strings
              normalized.core_fields[field] = value.filter(v => v && v !== '');
            } else if (value && typeof value === 'string' && value !== '') {
              normalized.core_fields[field] = [value];
            } else {
              normalized.core_fields[field] = [];
            }
          });
          
          // Normalize attributes (object with key/value pairs)
          if (normalized.core_fields.attributes) {
            if (typeof normalized.core_fields.attributes === 'object' && !Array.isArray(normalized.core_fields.attributes)) {
              // Filter out empty keys and values
              const attrs = {};
              Object.keys(normalized.core_fields.attributes).forEach(key => {
                const val = normalized.core_fields.attributes[key];
                if (key && key !== '' && val && typeof val === 'string' && val !== '') {
                  attrs[key] = val;
                }
              });
              normalized.core_fields.attributes = attrs;
            } else {
              normalized.core_fields.attributes = {};
            }
          } else {
            normalized.core_fields.attributes = {};
          }
        }

        return normalized;
      },
      loadSchema() {
        axios.get(this.baseApiUrl + '/detail/' + encodeURIComponent(this.schemaUid))
          .then(response => {
            if (!response.data || !response.data.schema) {
              throw new Error('Schema not found');
            }

            const schema = response.data.schema;

            if (Number(schema.is_core) === 1) {
              this.$alert(this.$t('core_schema_edit_forbidden'), { color: 'error' });
              this.$router.push({ name: 'schemas-list' });
              return;
            }

            this.currentSchema = schema;
            this.applySchemaValidation(schema);
            this.form.uid = schema.uid || '';
            this.form.title = schema.title || '';
            this.form.description = schema.description || '';
            this.form.metadata_options = this.normalizeMetadataOptions(schema.metadata_options || {});
            this.loadFiles();
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : 'Failed to load schema';
            this.$alert(message, { color: 'error' });
            this.$router.push({ name: 'schemas-list' });
          })
          .finally(() => {
            this.initializing = false;
          });
      },
      applySchemaValidation(schema) {
        if (!schema || typeof schema !== 'object') {
          this.reservedRootProperties = [];
          return;
        }
        this.reservedRootProperties = Array.isArray(schema.reserved_root_properties)
          ? schema.reserved_root_properties.slice()
          : [];
      },
      loadFiles() {
        if (this.isCreate || !this.schemaUid) {
          this.fileManifest = [];
          return Promise.resolve();
        }
        this.fileManifestLoading = true;
        return axios.get(this.baseApiUrl + '/files/' + encodeURIComponent(this.schemaUid))
          .then(response => {
            this.fileManifest = (response.data && response.data.files) ? response.data.files : [];

            const schemaFromFiles = response.data && response.data.schema ? response.data.schema : null;
            if (schemaFromFiles) {
              const mergedSchema = Object.assign({}, this.currentSchema || {}, schemaFromFiles);
              if ((!mergedSchema.filename || mergedSchema.filename === '') && this.currentSchema && this.currentSchema.filename) {
                mergedSchema.filename = this.currentSchema.filename;
              }
              this.currentSchema = mergedSchema;
            }

            if (this.currentSchema && this.currentSchema.filename) {
              this.fileManifest = this.fileManifest.map(file => {
                if (file && file.filename === this.currentSchema.filename) {
                  return Object.assign({}, file, { is_main: true });
                }
                return file;
              });
            }
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : this.$t('failed_to_load_schema_files');
            this.$alert(message, { color: 'error' });
          })
          .finally(() => {
            this.fileManifestLoading = false;
          });
      },
      handleMainFileSelection(event) {
        const file = event.target.files && event.target.files.length ? event.target.files[0] : null;
        if (!file) {
          return;
        }

        this.mainFile = {
          id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
          file,
          name: file.name,
          size: file.size,
          lastModified: file.lastModified
        };

        if (event.target) {
          event.target.value = '';
        }
      },
      removeMainFile() {
        this.mainFile = null;
      },
      handleAssociatedFilesSelection(event) {
        const files = event.target.files ? Array.from(event.target.files) : [];
        if (!files.length) {
          return;
        }

        const additions = files.map(file => ({
          id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
          file,
          name: file.name,
          size: file.size,
          lastModified: file.lastModified
        }));

        this.associatedFiles = this.associatedFiles.concat(additions);

        if (event.target) {
          event.target.value = '';
        }
      },
      removeAssociatedFile(item) {
        this.associatedFiles = this.associatedFiles.filter(file => file.id !== item.id);
      },
      clearAssociatedFiles() {
        this.associatedFiles = [];
      },
      clearStagedUploads() {
        this.mainFile = null;
        this.associatedFiles = [];
      },
      submit() {
        if (this.initializing) {
          return;
        }

        this.formErrorMessage = '';

        if (!this.$refs.form || !this.$refs.form.validate()) {
          return;
        }

        if (this.isCreate) {
          if (!this.mainFile) {
            const message = this.$t('main_schema_required');
            this.formErrorMessage = message;
            this.$alert(message, { color: 'error' });
            return;
          }
          this.createSchema();
          return;
        }

        this.updateSchema();
      },
      createSchema() {
        const mainItem = this.mainFile;
        if (!mainItem) {
          const message = this.$t('main_schema_required');
          this.formErrorMessage = message;
          this.$alert(message, { color: 'error' });
          return;
        }

        const relatedItems = this.associatedFiles || [];

        this.uploading = true;
        const formData = new FormData();
        formData.append('uid', this.form.uid);
        formData.append('title', this.form.title);
        formData.append('description', this.form.description);
        formData.append('metadata_options', JSON.stringify(this.form.metadata_options || {}));
        formData.append('main_schema', mainItem.file);

        relatedItems.forEach((item, index) => {
          formData.append(`schema_files[${index}]`, item.file);
        });

        axios.post(this.baseApiUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
          .then(() => {
            this.mainFile = null;
            this.associatedFiles = [];
            this.$router.push({ name: 'schemas-list' });
            this.$alert(this.$t('schema_created'), { color: 'success' });
            this.formErrorMessage = '';
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : 'Failed to create schema';
            this.formErrorMessage = message;
            this.$alert(message, { color: 'error' });
          })
          .finally(() => {
            this.uploading = false;
          });
      },
      async updateSchema() {
        this.uploading = true;
        const targetUid = this.schemaUid || this.form.uid;

        try {
          const formData = new FormData();
          formData.append('title', this.form.title);
          formData.append('description', this.form.description);
          formData.append('metadata_options', JSON.stringify(this.form.metadata_options || {}));

          await axios.post(this.baseApiUrl + '/update/' + encodeURIComponent(targetUid), formData);

          const hasMain = !!this.mainFile;
          const hasAssociated = this.associatedFiles.length > 0;

          if (hasMain || hasAssociated) {
            if (hasMain) {
              const replaceForm = new FormData();
              replaceForm.append('mode', 'replace_main');
              replaceForm.append('main_schema', this.mainFile.file);
              const response = await axios.post(this.baseApiUrl + '/files/' + encodeURIComponent(targetUid), replaceForm, {
                headers: { 'Content-Type': 'multipart/form-data' }
              });
              if (response.data && response.data.schema) {
                this.currentSchema = Object.assign({}, this.currentSchema, response.data.schema);
              }
              if (response.data && response.data.files) {
                this.fileManifest = response.data.files;
              }
            }

            if (hasAssociated) {
              const relatedForm = new FormData();
              relatedForm.append('mode', 'add_related');
              this.associatedFiles.forEach((item, index) => {
                relatedForm.append(`schema_files[${index}]`, item.file);
              });
              const response = await axios.post(this.baseApiUrl + '/files/' + encodeURIComponent(targetUid), relatedForm, {
                headers: { 'Content-Type': 'multipart/form-data' }
              });
              if (response.data && response.data.schema) {
                this.currentSchema = Object.assign({}, this.currentSchema, response.data.schema);
              }
              if (response.data && response.data.files) {
                this.fileManifest = response.data.files;
              }
            }

            this.mainFile = null;
            this.associatedFiles = [];
            await this.loadFiles();
          }

          this.$router.push({ name: 'schemas-list' });
          this.$alert(this.$t('schema_updated'), { color: 'success' });
          this.formErrorMessage = '';
        } catch (error) {
          const message = (error.response && error.response.data && error.response.data.message)
            ? error.response.data.message
            : 'Failed to update schema';
          this.formErrorMessage = message;
          this.$alert(message, { color: 'error' });
        } finally {
          this.uploading = false;
        }
      },
      async uploadSelectedFiles(options = { silentSuccess: false }) {
        if (!this.schemaUid) {
          return;
        }

        const hasMain = !!this.mainFile;
        const hasAssociated = this.associatedFiles.length > 0;

        if (!hasMain && !hasAssociated) {
          return;
        }

        this.uploadErrorMessage = '';

        this.pendingUploadLoading = true;

        try {
          if (hasMain) {
            const replaceForm = new FormData();
            replaceForm.append('mode', 'replace_main');
            replaceForm.append('main_schema', this.mainFile.file);
            const response = await axios.post(this.baseApiUrl + '/files/' + encodeURIComponent(this.schemaUid), replaceForm, {
              headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (response.data && response.data.schema) {
              this.currentSchema = response.data.schema;
              this.applySchemaValidation(response.data.schema);
            }
            if (response.data && response.data.files) {
              this.fileManifest = response.data.files;
            }
          }

          if (hasAssociated) {
            const relatedForm = new FormData();
            relatedForm.append('mode', 'add_related');
            this.associatedFiles.forEach((item, index) => {
              relatedForm.append(`schema_files[${index}]`, item.file);
            });
            const response = await axios.post(this.baseApiUrl + '/files/' + encodeURIComponent(this.schemaUid), relatedForm, {
              headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (response.data && response.data.schema) {
              this.currentSchema = response.data.schema;
              this.applySchemaValidation(response.data.schema);
            }
            if (response.data && response.data.files) {
              this.fileManifest = response.data.files;
            }
          }

          if (!options.silentSuccess) {
            this.$alert(this.$t('schema_files_updated'), { color: 'success' });
          }
          this.mainFile = null;
          this.associatedFiles = [];
          await this.loadFiles();
        } catch (error) {
          const message = (error.response && error.response.data && error.response.data.message)
            ? error.response.data.message
            : this.$t('schema_file_update_failed');
          this.uploadErrorMessage = message;
          this.$alert(message, { color: 'error' });
        } finally {
          this.pendingUploadLoading = false;
        }
      },
      deleteSchemaFile(file) {
        if (!file || !file.filename) {
          return;
        }
        this.$confirm(this.$t('delete_schema_file_confirm', { filename: file.filename }))
          .then(() => {
            this.$set(this.deletingFiles, file.filename, true);
            axios.delete(this.baseApiUrl + '/files/' + encodeURIComponent(this.schemaUid), {
                params: { filename: file.filename }
              })
              .then(response => {
                if (response.data && response.data.schema) {
                  this.currentSchema = response.data.schema;
                }
                if (response.data && response.data.files) {
                  this.fileManifest = response.data.files;
                }
                const message = (response.data && response.data.message)
                  ? response.data.message
                  : this.$t('schema_file_deleted');
                this.$alert(message, { color: 'success' });
              })
              .catch(error => {
                const message = (error.response && error.response.data && error.response.data.message)
                  ? error.response.data.message
                  : this.$t('schema_file_update_failed');
                this.$alert(message, { color: 'error' });
              })
              .finally(() => {
                this.$set(this.deletingFiles, file.filename, false);
              });
          })
          .catch(() => {});
      },
      isDeletingFile(filename) {
        return !!this.deletingFiles[filename];
      },
      formatFileSize(size) {
        if (size === null || size === undefined) {
          return '—';
        }
        if (size === 0) {
          return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const index = Math.floor(Math.log(size) / Math.log(1024));
        const value = size / Math.pow(1024, index);
        return `${value.toFixed(1)} ${units[index]}`;
      },
      cancel() {
        this.$router.push({ name: 'schemas-list' });
      }
    }
  };

  const SchemaMappings = {
    template: '#schema-mapping-template',
    props: {
      schemaUid: {
        type: String,
        default: ''
      }
    },
    data() {
      return {
        loading: false,
        saving: false,
        valid: true,
        form: {
          core_fields: {
            idno: [],
            title: [],
            country: [],
            year_start: [],
            year_end: [],
            attributes: {}
          }
        },
        formErrorMessage: '',
        currentSchema: null,
        metadataOptions: {},
        schemaTitle: '',
        fieldOptions: [],
        fieldsLoading: false
      };
    },
    computed: {
      baseApiUrl() {
        return baseApiRoot + 'api/schemas';
      }
    },
    watch: {
      schemaUid(newVal, oldVal) {
        if (newVal && newVal !== oldVal) {
          this.initialize();
        }
      }
    },
    created() {
      this.initialize();
    },
    methods: {
      initialize() {
        this.loading = true;
        this.saving = false;
        this.formErrorMessage = '';
        this.form.core_fields = {
          idno: [],
          title: [],
          country: [],
          year_start: [],
          year_end: [],
          attributes: {}
        };
        this.metadataOptions = {};
        this.currentSchema = null;
        this.schemaTitle = '';
        this.fieldOptions = [];
        this.fieldsLoading = false;

        this.fetchSchema()
          .then(() => this.fetchFields())
          .finally(() => {
            this.loading = false;
          });
      },
      normalizeMetadataOptions(options) {
        const defaults = {
          core_fields: {
            idno: [],
            title: []
          }
        };

        if (!options || typeof options !== 'object') {
          return JSON.parse(JSON.stringify(defaults));
        }

        const normalized = Object.assign({}, defaults, options);
        if (!normalized.core_fields || typeof normalized.core_fields !== 'object') {
          normalized.core_fields = Object.assign({}, defaults.core_fields);
        } else {
          // Normalize core_fields to arrays
          normalized.core_fields = Object.assign({}, defaults.core_fields);
          ['idno', 'title'].forEach(field => {
            const value = normalized.core_fields[field];
            if (Array.isArray(value)) {
              // Filter out empty strings
              normalized.core_fields[field] = value.filter(v => v && v !== '');
            } else if (value && typeof value === 'string' && value !== '') {
              normalized.core_fields[field] = [value];
            } else {
              normalized.core_fields[field] = [];
            }
          });
        }

        return normalized;
      },
      fetchSchema() {
        if (!this.schemaUid) {
          this.$router.push({ name: 'schemas-list' });
          return Promise.resolve();
        }

        return axios.get(this.baseApiUrl + '/detail/' + encodeURIComponent(this.schemaUid))
          .then(response => {
            if (!response.data || !response.data.schema) {
              throw new Error('Schema not found');
            }

            const schema = response.data.schema;

            this.currentSchema = schema;
            this.schemaTitle = schema.title || schema.uid || '';
            this.metadataOptions = schema.metadata_options || {};
            const normalized = this.normalizeMetadataOptions(this.metadataOptions);
            this.form.core_fields = Object.assign({}, normalized.core_fields);
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : 'Failed to load schema';
            this.$alert(message, { color: 'error' });
            this.$router.push({ name: 'schemas-list' });
            throw error;
          });
      },
      fetchFields() {
        if (!this.schemaUid) {
          this.fieldOptions = [];
          return Promise.resolve();
        }

        this.fieldsLoading = true;

        return axios.get(this.baseApiUrl + '/fields/' + encodeURIComponent(this.schemaUid), {
          params: { format: 'default' }
        })
          .then(response => {
            const fields = (response.data && Array.isArray(response.data.fields))
              ? response.data.fields
              : [];

            // Primitive types that are allowed for mapping
            const primitiveTypes = ['string', 'number', 'integer', 'boolean', 'null'];
            
            // Filter to only include fields with primitive types
            const primitiveFields = fields.filter(field => {
              if (!field || !field.path) {
                return false;
              }
              
              const fieldType = field.type;
              if (!fieldType || fieldType === '') {
                return false;
              }
              
              // Handle type as string or array of types
              if (typeof fieldType === 'string') {
                // Check if it's a primitive type (not object or array)
                return primitiveTypes.includes(fieldType);
              }
              
              // If type is an array, check if any primitive type is included
              // and exclude if object or array is present
              if (Array.isArray(fieldType)) {
                const hasObject = fieldType.includes('object');
                const hasArray = fieldType.includes('array');
                if (hasObject || hasArray) {
                  return false;
                }
                // Check if any primitive type is present
                return fieldType.some(t => primitiveTypes.includes(t));
              }
              
              return false;
            });

            this.fieldOptions = primitiveFields
              .map(field => field && field.path ? field.path : '')
              .filter(Boolean)
              .filter((value, index, self) => self.indexOf(value) === index)
              .sort();
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : this.$t('schema_mappings_update_failed');
            this.formErrorMessage = message;
          })
          .finally(() => {
            this.fieldsLoading = false;
          });
      },
      addAttribute() {
        if (!this.form.core_fields.attributes) {
          this.$set(this.form.core_fields, 'attributes', {});
        }
        // Create a new attribute with a default key
        const newKey = 'attribute_' + (Object.keys(this.form.core_fields.attributes).length + 1);
        this.$set(this.form.core_fields.attributes, newKey, '');
      },
      removeAttribute(key) {
        if (this.form.core_fields.attributes && this.form.core_fields.attributes[key]) {
          this.$delete(this.form.core_fields.attributes, key);
        }
      },
      updateAttributeKey(oldKey, newKey) {
        if (!newKey || newKey === '' || newKey === oldKey) {
          return;
        }
        // Check if new key already exists
        if (this.form.core_fields.attributes && this.form.core_fields.attributes[newKey]) {
          this.$alert(this.$t('attribute_key_exists'), { color: 'error' });
          return;
        }
        // Update the key
        const value = this.form.core_fields.attributes[oldKey];
        this.$delete(this.form.core_fields.attributes, oldKey);
        this.$set(this.form.core_fields.attributes, newKey, value);
      },
      updateAttributeValue(key, value) {
        if (!this.form.core_fields.attributes) {
          this.$set(this.form.core_fields, 'attributes', {});
        }
        // If value is an array (from combobox), take the first item or empty string
        const fieldValue = Array.isArray(value) ? (value.length > 0 ? value[0] : '') : (value || '');
        this.$set(this.form.core_fields.attributes, key, fieldValue);
      },
      submit() {
        if (this.loading || this.saving) {
          return;
        }

        this.formErrorMessage = '';
        this.saving = true;

        const targetUid = this.schemaUid;
        const existingTitle = this.currentSchema && this.currentSchema.title ? this.currentSchema.title : '';
        const existingDescription = this.currentSchema && typeof this.currentSchema.description === 'string'
          ? this.currentSchema.description
          : '';

        if (!existingTitle) {
          this.formErrorMessage = this.$t('schema_title_required');
          this.saving = false;
          return;
        }

        const metadataOptions = JSON.parse(JSON.stringify(this.metadataOptions || {}));
        if (!metadataOptions.core_fields || typeof metadataOptions.core_fields !== 'object') {
          metadataOptions.core_fields = {};
        }
        
        // Normalize to arrays and filter out empty values
        const normalizeField = (field) => {
          if (Array.isArray(field)) {
            const filtered = field.filter(v => v && v !== '');
            return filtered.length > 0 ? filtered : '';
          }
          if (field && typeof field === 'string' && field !== '') {
            return [field];
          }
          return '';
        };
        
        // Normalize simple array fields
        metadataOptions.core_fields.idno = normalizeField(this.form.core_fields.idno);
        metadataOptions.core_fields.title = normalizeField(this.form.core_fields.title);
        metadataOptions.core_fields.country = normalizeField(this.form.core_fields.country);
        metadataOptions.core_fields.year_start = normalizeField(this.form.core_fields.year_start);
        metadataOptions.core_fields.year_end = normalizeField(this.form.core_fields.year_end);
        
        // Validate required fields
        if (!metadataOptions.core_fields.idno || metadataOptions.core_fields.idno === '') {
          this.formErrorMessage = this.$t('idno_required');
          this.saving = false;
          return;
        }
        
        if (!metadataOptions.core_fields.title || metadataOptions.core_fields.title === '') {
          this.formErrorMessage = this.$t('title_required');
          this.saving = false;
          return;
        }
        
        // Normalize attributes (object with key/value pairs)
        if (this.form.core_fields.attributes && typeof this.form.core_fields.attributes === 'object' && !Array.isArray(this.form.core_fields.attributes)) {
          const attrs = {};
          Object.keys(this.form.core_fields.attributes).forEach(key => {
            const val = this.form.core_fields.attributes[key];
            if (key && key !== '' && val && typeof val === 'string' && val !== '') {
              attrs[key] = val;
            }
          });
          metadataOptions.core_fields.attributes = Object.keys(attrs).length > 0 ? attrs : {};
        } else {
          metadataOptions.core_fields.attributes = {};
        }

        const formData = new FormData();
        formData.append('title', existingTitle);
        formData.append('description', existingDescription);
        formData.append('metadata_options', JSON.stringify(metadataOptions));

        axios.post(this.baseApiUrl + '/update/' + encodeURIComponent(targetUid), formData)
          .then(() => {
            this.$alert(this.$t('schema_mappings_updated'), { color: 'success' });
            this.$router.back();
          })
          .catch(error => {
            const message = (error.response && error.response.data && error.response.data.message)
              ? error.response.data.message
              : this.$t('schema_mappings_update_failed');
            this.formErrorMessage = message;
          })
          .finally(() => {
            this.saving = false;
          });
      }
    }
  };

  const routes = [
    { path: '/', name: 'schemas-list', component: SchemaList },
    { path: '/create', name: 'create-schema', component: SchemaForm, props: { mode: 'create' } },
    { path: '/edit/:uid', name: 'edit-schema', component: SchemaForm, props: route => ({ mode: 'edit', schemaUid: route.params.uid }) },
    { path: '/mappings/:uid', name: 'schema-mappings', component: SchemaMappings, props: route => ({ schemaUid: route.params.uid }) }
  ];

  const router = new VueRouter({
    mode: 'hash',
    routes
  });

  new Vue({
    el: '#app',
    i18n,
    vuetify,
    router,
    data() {
      return {
        baseApiRoot,
        navTabsModel: 3  // 0=projects, 1=collections, 2=templates, 3=schemas
      };
    },
    watch: {
      '$route'(to, from) {
        // Ensure schemas tab stays active when on schemas routes
        if (to && to.name && to.name.startsWith('schema')) {
          this.navTabsModel = 3;
        }
      }
    },
    created() {
      // Set active tab to schemas (index 3) when component is created
      this.navTabsModel = 3;
    },
    methods: {
      pageLink(page) {
        const site = (typeof CI !== 'undefined' && CI.site_url)
          ? CI.site_url.replace(/\/+$/, '')
          : '';
        if (!site) {
          return;
        }
        window.location.href = site + '/' + page;
      }
    }
  });
})();
