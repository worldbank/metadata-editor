//date field control — ISO formats from template display_options.format
Vue.component('editor-date-field', {
    props: ['value','field'],
    data: function () {
      return {
        menu1: false,
        draft: '',
        pickerDate: '',
        pickerTime: '00:00'
      }
    },
    watch: {
      value: {
        immediate: true,
        handler: function (val) {
          this.draft = this.displayFromValue(val);
          this.syncPickerFromValue(val);
        }
      }
    },
    computed:{
      isFieldReadOnly() {
        if (!this.$store.getters.getUserHasEditAccess) {
          return true;
        }

        return this.field && this.field.is_readonly;
      },
      dateFormat() {
        if (typeof FieldValidationRulesUtil !== 'undefined' && FieldValidationRulesUtil.resolveDateFormat) {
          return FieldValidationRulesUtil.resolveDateFormat(this.field);
        }
        var fmt = this.field && this.field.display_options && this.field.display_options.format;
        if (fmt === 'datetime_iso') {
          return 'datetime';
        }
        if (['partial', 'date', 'year-month', 'year', 'datetime'].indexOf(fmt) !== -1) {
          return fmt;
        }
        return 'partial';
      },
      pickerType() {
        return this.dateFormat === 'year-month' ? 'month' : 'date';
      },
      formatHint() {
        switch (this.dateFormat) {
          case 'date':
            return 'Date format: YYYY-MM-DD';
          case 'year-month':
            return 'Date format: YYYY-MM';
          case 'year':
            return 'Date format: YYYY';
          case 'datetime':
            return 'Date format: YYYY-MM-DDTHH:mm:ssZ';
          default:
            return 'Date format: YYYY, YYYY-MM, or YYYY-MM-DD';
        }
      },
      yearOptions() {
        var current = new Date().getFullYear();
        var years = [];
        for (var y = current + 10; y >= 1800; y--) {
          years.push(String(y));
        }
        return years;
      }
    },
    methods:{
      displayFromValue(value) {
        if (value === null || value === undefined || value === '') {
          return '';
        }
        var s = String(value);
        if (this.dateFormat === 'datetime') {
          return s;
        }
        if (/^\d{4}-\d{2}-\d{2}T/.test(s)) {
          s = s.slice(0, 10);
        }
        if (this.dateFormat === 'year' && /^\d{4}/.test(s)) {
          return s.slice(0, 4);
        }
        if (this.dateFormat === 'year-month' && /^\d{4}-\d{2}/.test(s)) {
          return s.slice(0, 7);
        }
        return s;
      },
      syncPickerFromValue(value) {
        if (!value) {
          this.pickerDate = '';
          this.pickerTime = '00:00';
          return;
        }
        var s = String(value);
        if (/^\d{4}-\d{2}-\d{2}T/.test(s)) {
          this.pickerDate = s.slice(0, 10);
          var timeMatch = s.match(/T(\d{2}:\d{2})/);
          this.pickerTime = timeMatch ? timeMatch[1] : '00:00';
          return;
        }
        if (this.dateFormat === 'year-month' && /^\d{4}-\d{2}$/.test(s)) {
          this.pickerDate = s;
          return;
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
          this.pickerDate = s;
        }
      },
      isValidForFormat(text) {
        if (!text) {
          return true;
        }
        if (typeof FieldValidationRulesUtil === 'undefined') {
          return true;
        }
        switch (this.dateFormat) {
          case 'date':
            return FieldValidationRulesUtil.isIsoDate(text);
          case 'year-month':
            return FieldValidationRulesUtil.isIsoYearMonth(text);
          case 'year':
            return FieldValidationRulesUtil.isIsoYear(text);
          case 'datetime':
            return FieldValidationRulesUtil.isIsoDateTime(text);
          default:
            return FieldValidationRulesUtil.isIsoDatePartial(text);
        }
      },
      commitValue(next) {
        var currentDisplay = this.displayFromValue(this.value);
        if (next === currentDisplay) {
          this.draft = currentDisplay;
          return;
        }
        this.draft = next;
        this.$emit('input', next === '' ? null : next);
      },
      commitDraft() {
        var next = (this.draft || '').trim();
        this.commitValue(next);
      },
      clearValue() {
        this.draft = '';
        this.pickerDate = '';
        this.pickerTime = '00:00';
        this.$emit('input', null);
      },
      openPicker() {
        if (this.isFieldReadOnly) {
          return;
        }
        this.menu1 = true;
      },
      onPickerChange(val) {
        if (!val) {
          return;
        }
        this.pickerDate = val;
        if (this.dateFormat === 'datetime') {
          this.emitDateTime();
          return;
        }
        if (this.dateFormat === 'year-month') {
          this.commitValue(String(val).slice(0, 7));
        } else if (this.dateFormat === 'year') {
          this.commitValue(String(val).slice(0, 4));
        } else {
          this.commitValue(String(val).slice(0, 10));
        }
        this.menu1 = false;
      },
      onYearPick(year) {
        this.commitValue(String(year));
        this.menu1 = false;
      },
      emitDateTime() {
        if (!this.pickerDate) {
          return;
        }
        var time = this.pickerTime || '00:00';
        var iso = moment(this.pickerDate + ' ' + time, 'YYYY-MM-DD HH:mm').toISOString();
        this.commitValue(iso);
      }
    },
    template: `
    <div class="date-field">
        <v-menu
          v-model="menu1"
          :close-on-content-click="false"
          offset-y
          max-width="290"
        >
          <template v-slot:activator="{ attrs }">
            <v-text-field
              v-model="draft"
              clearable
              v-bind="attrs"
              dense
              solo
              prepend-inner-icon="mdi-calendar"
              @click:prepend-inner="openPicker"
              @blur="commitDraft"
              @click:clear="clearValue"
              @keydown.enter.prevent="commitDraft"
              :hint="formatHint"
              persistent-hint
              :disabled="isFieldReadOnly"
              :error="!!draft && !isValidForFormat(draft)"
            ></v-text-field>
          </template>
          <v-card v-if="dateFormat=='year'">
            <v-list dense class="overflow-y-auto" max-height="280">
              <v-list-item
                v-for="year in yearOptions"
                :key="year"
                @click="onYearPick(year)"
              >
                <v-list-item-title>{{ year }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card>
          <div v-else>
            <v-date-picker
              :value="pickerDate"
              :type="pickerType"
              @change="onPickerChange"
              :disabled="isFieldReadOnly"
            ></v-date-picker>
            <v-time-picker
              v-if="dateFormat=='datetime'"
              v-model="pickerTime"
              format="24hr"
              @change="emitDateTime"
              :disabled="isFieldReadOnly"
            ></v-time-picker>
          </div>
        </v-menu>
    </div>
    `
  });
