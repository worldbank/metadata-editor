function projectTranslationLanguageItems() {
    var names = {
        aa: 'Afar', ab: 'Abkhazian', ae: 'Avestan', af: 'Afrikaans', ak: 'Akan', am: 'Amharic',
        an: 'Aragonese', ar: 'Arabic', as: 'Assamese', av: 'Avaric', ay: 'Aymara', az: 'Azerbaijani',
        ba: 'Bashkir', be: 'Belarusian', bg: 'Bulgarian', bi: 'Bislama', bm: 'Bambara', bn: 'Bengali',
        bo: 'Tibetan', br: 'Breton', bs: 'Bosnian', ca: 'Catalan', ce: 'Chechen', ch: 'Chamorro',
        co: 'Corsican', cr: 'Cree', cs: 'Czech', cu: 'Church Slavic', cv: 'Chuvash', cy: 'Welsh',
        da: 'Danish', de: 'German', dv: 'Divehi', dz: 'Dzongkha', ee: 'Ewe', el: 'Greek',
        en: 'English', eo: 'Esperanto', es: 'Spanish', et: 'Estonian', eu: 'Basque', fa: 'Persian',
        ff: 'Fulah', fi: 'Finnish', fj: 'Fijian', fo: 'Faroese', fr: 'French', fy: 'Western Frisian',
        ga: 'Irish', gd: 'Scottish Gaelic', gl: 'Galician', gn: 'Guarani', gu: 'Gujarati', gv: 'Manx',
        ha: 'Hausa', he: 'Hebrew', hi: 'Hindi', ho: 'Hiri Motu', hr: 'Croatian', ht: 'Haitian',
        hu: 'Hungarian', hy: 'Armenian', hz: 'Herero', ia: 'Interlingua', id: 'Indonesian',
        ie: 'Interlingue', ig: 'Igbo', ii: 'Sichuan Yi', ik: 'Inupiaq', io: 'Ido', is: 'Icelandic',
        it: 'Italian', iu: 'Inuktitut', ja: 'Japanese', jv: 'Javanese', ka: 'Georgian', kg: 'Kongo',
        ki: 'Kikuyu', kj: 'Kuanyama', kk: 'Kazakh', kl: 'Kalaallisut', km: 'Khmer', kn: 'Kannada',
        ko: 'Korean', kr: 'Kanuri', ks: 'Kashmiri', ku: 'Kurdish', kv: 'Komi', kw: 'Cornish',
        ky: 'Kyrgyz', la: 'Latin', lb: 'Luxembourgish', lg: 'Ganda', li: 'Limburgan', ln: 'Lingala',
        lo: 'Lao', lt: 'Lithuanian', lu: 'Luba-Katanga', lv: 'Latvian', mg: 'Malagasy', mh: 'Marshallese',
        mi: 'Maori', mk: 'Macedonian', ml: 'Malayalam', mn: 'Mongolian', mr: 'Marathi', ms: 'Malay',
        mt: 'Maltese', my: 'Burmese', na: 'Nauru', nb: 'Norwegian Bokmål', nd: 'North Ndebele',
        ne: 'Nepali', ng: 'Ndonga', nl: 'Dutch', nn: 'Norwegian Nynorsk', no: 'Norwegian',
        nr: 'South Ndebele', nv: 'Navajo', ny: 'Chichewa', oc: 'Occitan', oj: 'Ojibwa', om: 'Oromo',
        or: 'Oriya', os: 'Ossetian', pa: 'Punjabi', pi: 'Pali', pl: 'Polish', ps: 'Pashto',
        pt: 'Portuguese', qu: 'Quechua', rm: 'Romansh', rn: 'Rundi', ro: 'Romanian', ru: 'Russian',
        rw: 'Kinyarwanda', sa: 'Sanskrit', sc: 'Sardinian', sd: 'Sindhi', se: 'Northern Sami',
        sg: 'Sango', si: 'Sinhala', sk: 'Slovak', sl: 'Slovenian', sm: 'Samoan', sn: 'Shona',
        so: 'Somali', sq: 'Albanian', sr: 'Serbian', ss: 'Swati', st: 'Southern Sotho', su: 'Sundanese',
        sv: 'Swedish', sw: 'Swahili', ta: 'Tamil', te: 'Telugu', tg: 'Tajik', th: 'Thai',
        ti: 'Tigrinya', tk: 'Turkmen', tl: 'Tagalog', tn: 'Tswana', to: 'Tonga', tr: 'Turkish',
        ts: 'Tsonga', tt: 'Tatar', tw: 'Twi', ty: 'Tahitian', ug: 'Uighur', uk: 'Ukrainian',
        ur: 'Urdu', uz: 'Uzbek', ve: 'Venda', vi: 'Vietnamese', vo: 'Volapük', wa: 'Walloon',
        wo: 'Wolof', xh: 'Xhosa', yi: 'Yiddish', yo: 'Yoruba', za: 'Zhuang', zh: 'Chinese',
        zu: 'Zulu'
    };
    var extras = [
        { code: 'pt-br', name: 'Portuguese (Brazil)' },
        { code: 'pt-pt', name: 'Portuguese (Portugal)' },
        { code: 'zh-cn', name: 'Chinese (Simplified)' },
        { code: 'zh-tw', name: 'Chinese (Traditional)' },
        { code: 'en-gb', name: 'English (United Kingdom)' },
        { code: 'en-us', name: 'English (United States)' },
        { code: 'es-419', name: 'Spanish (Latin America)' }
    ];
    var items = Object.keys(names).sort().map(function (code) {
        return { code: code, display: names[code] + ' (' + code + ')', name: names[code] };
    });
    extras.forEach(function (item) {
        items.push({ code: item.code, display: item.name + ' (' + item.code + ')', name: item.name });
    });
    return items;
}

function projectTranslationLanguageCode(value) {
    if (!value) {
        return '';
    }
    if (typeof value === 'object') {
        return value.code || '';
    }
    var raw = String(value).trim();
    var lower = raw.toLowerCase().replace(/_/g, '-');
    var items = projectTranslationLanguageItems();
    var byCode = items.find(function (item) {
        return item.code === lower;
    });
    if (byCode) {
        return byCode.code;
    }
    var byName = items.find(function (item) {
        return item.name.toLowerCase() === lower || item.display.toLowerCase() === lower;
    });
    if (byName) {
        return byName.code;
    }
    return lower;
}

function projectTranslationLanguageLabel(code) {
    if (!code) {
        return '';
    }
    var found = projectTranslationLanguageItems().find(function (item) {
        return item.code === code;
    });
    return found ? found.display : code;
}

function projectTranslationsUiEnabled(type) {
    var t = String(type || '').toLowerCase();
    return t === 'indicator' || t === 'timeseries' || t === 'indicator-db' || t === 'timeseries-db';
}
