/*
 * <pslm-server-chat>: the Metadata Editor's server-backed assistant panel.
 *
 * The model, retrieval and tools run on the FastAPI service; this element only talks to the editor's
 * own proxy (api/assistant/status, api/assistant/chat), which checks the session and project ACL and
 * relays the service's Server-Sent Events (protocol pslm-server/1). Nothing here reads the page: the
 * editor declares what the user is looking at through `pslm-route-change`, and the proxy reads the
 * project server-side.
 *
 * There is no write path. A proposal is a card; "Fill in form" dispatches `assistant-fill-request`,
 * which server-assistant-fill.js answers by typing the value into the editor's own field (unsaved).
 *
 * Framework-free and build-free, styled inside a shadow root so the editor's global CSS cannot reach
 * it; colours are CSS custom properties a host may override on the element. See README-assistant.md.
 *
 * Attributes: data-api (editor base URL), data-record-id (project id or empty), data-launcher-offset
 * (CSS length above the bottom edge for the launcher).
 */
(function () {
  'use strict';

  var PROTOCOL = 'pslm-server/1';
  var MAX_QUESTION = 4000;
  var HISTORY_MESSAGES = 6;
  var STORE = 'server-assistant:';

  // ------------------------------------------------------------------ markdown (safe subset)
  // Built with createElement/textContent only; no HTML from the model is ever parsed. Links are shown
  // as "text (url)" and are not clickable: a model can invent a URL, and a clickable invented link is
  // worse than a visible one.

  function inline(parent, text) {
    var parts = String(text).split(/(`[^`\n]+`)/);
    parts.forEach(function (part) {
      if (/^`[^`\n]+`$/.test(part)) {
        var code = document.createElement('code');
        code.textContent = part.slice(1, -1);
        parent.appendChild(code);
        return;
      }
      var rest = part.replace(/!?\[([^\]]+)\]\(([^)\s]+)\)/g, '$1 ($2)');
      var re = /(\*\*[^*\s](?:[^*\n]*[^*\s])?\*\*|__[^_\s](?:[^_\n]*[^_\s])?__|\*[^*\s](?:[^*\n]*[^*\s])?\*|(?:^|(?<=[\s(]))_[^_\s](?:[^_\n]*[^_\s])?_(?=[\s.,;:!?)]|$))/g;
      var last = 0;
      var m;
      while ((m = re.exec(rest))) {
        if (m.index > last) parent.appendChild(document.createTextNode(rest.slice(last, m.index)));
        var token = m[0];
        var strong = token.slice(0, 2) === '**' || token.slice(0, 2) === '__';
        var el = document.createElement(strong ? 'strong' : 'em');
        el.textContent = strong ? token.slice(2, -2) : token.slice(1, -1);
        parent.appendChild(el);
        last = m.index + token.length;
      }
      if (last < rest.length) parent.appendChild(document.createTextNode(rest.slice(last)));
    });
  }

  function cells(row) {
    return row.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) { return c.trim(); });
  }

  function renderMarkdown(source) {
    var root = document.createDocumentFragment();
    var lines = String(source || '').replace(/\r\n?/g, '\n').split('\n');
    var i = 0;
    var para = [];
    function flushPara() {
      if (!para.length) return;
      var p = document.createElement('p');
      inline(p, para.join(' '));
      root.appendChild(p);
      para = [];
    }
    while (i < lines.length) {
      var line = lines[i];
      var fence = /^\s*```\s*([\w+-]*)\s*$/.exec(line);
      if (fence) {
        flushPara();
        var body = [];
        i++;
        while (i < lines.length && !/^\s*```\s*$/.test(lines[i])) body.push(lines[i++]);
        i++; // closing fence (an unterminated fence still renders as code)
        var pre = document.createElement('pre');
        var code = document.createElement('code');
        code.textContent = body.join('\n');
        if (fence[1]) pre.dataset.lang = fence[1];
        pre.appendChild(code);
        root.appendChild(pre);
        continue;
      }
      if (!line.trim()) { flushPara(); i++; continue; }
      var heading = /^(#{1,6})\s+(.*)$/.exec(line);
      if (heading) {
        flushPara();
        var h = document.createElement('h' + Math.min(6, heading[1].length + 3));
        inline(h, heading[2]);
        root.appendChild(h);
        i++;
        continue;
      }
      if (/^ {0,3}([-*_])\s*(?:\1\s*){2,}$/.test(line)) { flushPara(); root.appendChild(document.createElement('hr')); i++; continue; }
      if (/^\s*\|.*\|\s*$/.test(line) && i + 1 < lines.length && /^\s*\|?(\s*:?-{3,}:?\s*\|)+\s*(:?-{3,}:?)?\s*\|?\s*$/.test(lines[i + 1])) {
        flushPara();
        var wrap = document.createElement('div');
        wrap.className = 'table';
        var table = document.createElement('table');
        var thead = document.createElement('thead');
        var tr = document.createElement('tr');
        cells(line).forEach(function (c) { var th = document.createElement('th'); inline(th, c); tr.appendChild(th); });
        thead.appendChild(tr);
        table.appendChild(thead);
        var tbody = document.createElement('tbody');
        i += 2;
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
          var r = document.createElement('tr');
          cells(lines[i]).forEach(function (c) { var td = document.createElement('td'); inline(td, c); r.appendChild(td); });
          tbody.appendChild(r);
          i++;
        }
        table.appendChild(tbody);
        wrap.appendChild(table);
        root.appendChild(wrap);
        continue;
      }
      if (/^ {0,3}>/.test(line)) {
        flushPara();
        var quote = [];
        while (i < lines.length && /^ {0,3}>/.test(lines[i])) quote.push(lines[i++].replace(/^ {0,3}> ?/, ''));
        var bq = document.createElement('blockquote');
        bq.appendChild(renderMarkdown(quote.join('\n')));
        root.appendChild(bq);
        continue;
      }
      var item = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(line);
      if (item) {
        flushPara();
        i = renderList(lines, i, root);
        continue;
      }
      para.push(line.trim());
      i++;
    }
    flushPara();
    return root;
  }

  // Nested lists by indentation, any depth; an ordered list keeps its starting number.
  function renderList(lines, i, parent) {
    var first = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(lines[i]);
    var indent = first[1].length;
    var ordered = /\d/.test(first[2]);
    var list = document.createElement(ordered ? 'ol' : 'ul');
    if (ordered && parseInt(first[2], 10) !== 1) list.start = parseInt(first[2], 10);
    var li = null;
    while (i < lines.length) {
      var line = lines[i];
      if (!line.trim()) {
        // A blank line ends the list unless the next line continues it.
        var next = lines[i + 1] || '';
        var nextItem = /^(\s*)([-*+]|\d+[.)])\s+/.exec(next);
        if (nextItem && nextItem[1].length >= indent) { i++; continue; }
        if (li && /^\s{2,}\S/.test(next)) { i++; continue; }
        break;
      }
      var m = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/.exec(line);
      if (m && m[1].length > indent && li) { i = renderList(lines, i, li); continue; }
      if (m && m[1].length < indent) break;
      if (m && /\d/.test(m[2]) !== ordered) break;
      if (m) {
        li = document.createElement('li');
        inline(li, m[3]);
        list.appendChild(li);
        i++;
        continue;
      }
      if (li && /^\s+\S/.test(line)) { li.appendChild(document.createTextNode(' ')); inline(li, line.trim()); i++; continue; }
      break;
    }
    parent.appendChild(list);
    return i;
  }

  // ------------------------------------------------------------------ helpers

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'text') node.textContent = v;
      else if (k === 'class') node.className = v;
      else if (k.slice(0, 2) === 'on') node.addEventListener(k.slice(2), v);
      else node.setAttribute(k, v === true ? '' : v);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return node;
  }

  var ICONS = {
    spark: 'M12 2l1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8zM19 14l.9 2.6L22.5 17.5l-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9z',
    close: 'M6.4 5L5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z',
    send: 'M3 20.5v-6l8-2.5-8-2.5v-6L22 12z',
    stop: 'M7 7h10v10H7z',
    plus: 'M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z',
    copy: 'M8 3h11v13h-2V5H8zM5 7h11v14H5zm2 2v10h7V9z',
    retry: 'M12 5V2L7 6l5 4V7a5 5 0 11-5 5H5a7 7 0 107-7z',
    check: 'M9.5 16.2L5.3 12l-1.4 1.4 5.6 5.6L20.1 8.4 18.7 7z',
    x: 'M6.4 5L5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z',
    down: 'M12 16l-6-6 1.4-1.4 4.6 4.6 4.6-4.6L18 10z',
    info: 'M11 7h2v2h-2zm0 4h2v6h-2zm1-9a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16z',
    warn: 'M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z',
    doc: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zm2 16H8v-2h8zm0-4H8v-2h8zm-3-5V3.5L18.5 9z',
    clip: 'M16.5 6.5l-7.9 7.9a3 3 0 104.2 4.2l7.2-7.2a5 5 0 10-7.1-7.1L4.6 12.6a7 7 0 109.9 9.9l6-6-1.4-1.4-6 6a5 5 0 11-7.1-7.1l8.3-8.3a3 3 0 114.2 4.2l-7.2 7.2a1 1 0 11-1.4-1.4l7.9-7.9z'
  };

  function icon(name) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ICONS[name]);
    svg.appendChild(path);
    return svg;
  }

  function iconButton(name, label, onclick, extraClass) {
    return el('button', { type: 'button', class: 'icon-btn ' + (extraClass || ''), 'aria-label': label, title: label, onclick: onclick }, [icon(name)]);
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = el('textarea', { style: 'position:fixed;left:-9999px' });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy') ? resolve() : reject(new Error('copy refused')); } catch (e) { reject(e); }
      ta.remove();
    });
  }

  function parseSse(buffer, onEvent) {
    var blocks = buffer.split('\n\n');
    var rest = blocks.pop();
    blocks.forEach(function (block) {
      var name = 'message';
      var data = [];
      block.split('\n').forEach(function (line) {
        if (line.indexOf('event:') === 0) name = line.slice(6).trim();
        else if (line.indexOf('data:') === 0) data.push(line.slice(5).replace(/^ /, ''));
      });
      if (!data.length) return;
      try { onEvent(name, JSON.parse(data.join('\n'))); } catch (e) { /* malformed event: skip */ }
    });
    return rest;
  }

  var STATUS_LABEL = { empty: 'Empty field', inconsistent: 'Inconsistent', improvement: 'Improvement' };

  // Provider errors arrive as library text ("litellm.InternalServerError: ... Connection error").
  // The reader gets what happened and what to do; the original stays one click away.
  function friendlyError(raw) {
    // Checked first: "APIConnectionError: ... No module named x" is a broken install, not a network fault.
    if (/no module named|importerror|modulenotfound/i.test(raw)) return 'The assistant service is missing a component and cannot answer. An administrator needs to check its installation.';
    if (/connect|refused|unreachable|name or service|timed? ?out/i.test(raw)) return 'The model server is not responding. It may be starting up or stopped. Try again in a moment.';
    if (/rate.?limit|429|quota/i.test(raw)) return 'The model provider is rate-limiting requests. Wait a moment, then retry.';
    if (/auth|api.?key|401|403/i.test(raw)) return 'The model provider rejected the assistant\'s credentials. An administrator needs to check the configuration.';
    if (/context|too long|maximum.*tokens/i.test(raw)) return 'The conversation is too long for this model. Start a new chat.';
    return 'The model could not answer this time. Retry, or start a new chat.';
  }

  // ------------------------------------------------------------------ styles

  var CSS = [
    ':host{--sa-accent:#1565c0;--sa-accent-ink:#fff;--sa-bg:#fff;--sa-surface:#f5f7fa;--sa-border:#d8dee6;--sa-ink:#1d2733;',
    '--sa-muted:#5b6876;--sa-user:#e8f0fe;--sa-warn-bg:#fff7e0;--sa-warn:#8a5a00;--sa-err-bg:#fdecea;--sa-err:#b3261e;--sa-ok:#1e7d34;',
    '--sa-width:420px;--sa-font:Roboto,"Segoe UI",system-ui,sans-serif;all:initial;font-family:var(--sa-font);color:var(--sa-ink)}',
    '*{box-sizing:border-box}',
    'button{font:inherit;color:inherit}',
    '.launcher{position:fixed;right:20px;bottom:var(--sa-launcher-offset,20px);z-index:1201;display:inline-flex;align-items:center;gap:8px;',
    'padding:10px 16px 10px 12px;border:0;border-radius:999px;background:var(--sa-accent);color:var(--sa-accent-ink);font-size:14px;font-weight:500;',
    'box-shadow:0 4px 14px rgba(21,101,192,.35);cursor:pointer}',
    '.launcher svg{width:20px;height:20px;fill:currentColor}',
    '.launcher:hover{filter:brightness(1.08)}',
    '.launcher[aria-expanded=true]{display:none}',
    '.panel{position:fixed;top:var(--sa-top,0);right:0;bottom:0;width:var(--sa-width);min-width:340px;max-width:min(760px,100vw);z-index:1202;',
    'display:flex;flex-direction:column;background:var(--sa-bg);border-left:1px solid var(--sa-border);box-shadow:-8px 0 24px rgba(16,24,40,.12);font-size:14px;line-height:1.5}',
    '.panel[hidden]{display:none}',
    '.resizer{position:absolute;left:-4px;top:0;bottom:0;width:8px;cursor:ew-resize;z-index:2;background:transparent}',
    '.resizer:hover,.resizer:focus-visible{background:linear-gradient(to right,transparent 3px,var(--sa-accent) 3px,var(--sa-accent) 5px,transparent 5px);outline:none}',
    'header{display:flex;align-items:flex-start;gap:8px;padding:12px 12px 10px 16px;border-bottom:1px solid var(--sa-border)}',
    '.title{flex:1;min-width:0}',
    '.title h2{margin:0;font-size:16px;font-weight:600;display:flex;align-items:center;gap:6px}',
    '.title h2 svg{width:18px;height:18px;fill:var(--sa-accent)}',
    '.context{margin-top:2px;font-size:12px;color:var(--sa-muted);display:flex;flex-direction:column}',
    '.context span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
    '.context .where{font-size:11.5px}',
    '.icon-btn{display:inline-grid;place-items:center;width:32px;height:32px;border:0;border-radius:8px;background:transparent;cursor:pointer;color:var(--sa-muted)}',
    '.icon-btn:hover{background:var(--sa-surface);color:var(--sa-ink)}',
    '.icon-btn svg{width:18px;height:18px;fill:currentColor}',
    ':focus-visible{outline:2px solid var(--sa-accent);outline-offset:2px}',
    '.modelbar{display:flex;align-items:center;gap:8px;padding:8px 16px;border-bottom:1px solid var(--sa-border);font-size:12px;color:var(--sa-muted)}',
    '.modelbar select{font:inherit;color:var(--sa-ink);border:1px solid var(--sa-border);border-radius:6px;padding:3px 6px;background:var(--sa-bg);max-width:60%}',
    '.dot{width:8px;height:8px;border-radius:50%;background:var(--sa-ok);flex:none}',
    '.dot.off{background:#9aa5b1}',
    '.modelbar .keep{margin-left:auto;display:inline-flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap}',
    '.tag{border:1px solid var(--sa-border);border-radius:999px;padding:0 8px;font-size:11px;white-space:nowrap}',
    '.tag.ext{background:var(--sa-warn-bg);color:var(--sa-warn);border-color:transparent;cursor:help}',
    '.banner{margin:8px 12px 0;padding:8px 10px;border-radius:8px;font-size:12px;display:flex;gap:8px;align-items:flex-start}',
    '.banner svg{width:16px;height:16px;flex:none;fill:currentColor;margin-top:1px}',
    '.banner.warn{background:var(--sa-warn-bg);color:var(--sa-warn)}',
    '.banner span{flex:1}.banner .btn{white-space:nowrap;padding:2px 10px;font-size:12px}',
    '.banner.err{background:var(--sa-err-bg);color:var(--sa-err)}',
    '.log{flex:1;overflow-y:auto;padding:12px 16px 4px;display:flex;flex-direction:column;gap:14px}',
    '.empty{margin:auto 0;padding:8px 0}',
    '.empty h3{margin:0 0 4px;font-size:15px}',
    '.empty p{margin:0 0 12px;color:var(--sa-muted);font-size:13px}',
    '.suggestions{display:flex;flex-direction:column;gap:8px}',
    '.suggestion{text-align:left;border:1px solid var(--sa-border);border-radius:10px;background:var(--sa-bg);padding:8px 12px;cursor:pointer;font-size:13px}',
    '.suggestion:hover{border-color:var(--sa-accent);background:var(--sa-surface)}',
    '.turn{display:flex;flex-direction:column;gap:8px}',
    '.q{align-self:flex-end;max-width:88%;background:var(--sa-user);border-radius:14px 14px 4px 14px;padding:8px 12px;white-space:pre-wrap;word-wrap:break-word}',
    '.a{max-width:100%}',
    '.answer{word-wrap:break-word}',
    '.answer p{margin:0 0 8px}.answer p:last-child{margin-bottom:0}',
    '.answer ul,.answer ol{margin:4px 0 8px;padding-left:22px}.answer li{margin:2px 0}',
    '.answer code{font:12.5px ui-monospace,SFMono-Regular,Menlo,monospace;background:var(--sa-surface);border-radius:4px;padding:1px 4px}',
    '.answer pre{background:var(--sa-surface);border-radius:8px;padding:8px 10px;overflow-x:auto;margin:6px 0}',
    '.answer pre code{background:none;padding:0}',
    '.answer h4,.answer h5,.answer h6{margin:10px 0 4px;font-size:14px}',
    '.answer blockquote{margin:6px 0;padding-left:10px;border-left:3px solid var(--sa-border);color:var(--sa-muted)}',
    '.answer hr{border:0;border-top:1px solid var(--sa-border);margin:10px 0}',
    '.answer .table{overflow-x:auto}.answer table{border-collapse:collapse;font-size:12.5px;margin:6px 0}',
    '.answer th,.answer td{border:1px solid var(--sa-border);padding:4px 8px;text-align:left;vertical-align:top}.answer th{background:var(--sa-surface)}',
    '.streaming .answer::after{content:"";display:inline-block;width:7px;height:15px;margin-left:2px;vertical-align:text-bottom;background:var(--sa-accent);animation:blink 1s steps(2) infinite}',
    '@keyframes blink{50%{opacity:0}}',
    '.working{display:flex;align-items:center;gap:8px;color:var(--sa-muted);font-size:13px}',
    '.spinner{width:14px;height:14px;border:2px solid var(--sa-border);border-top-color:var(--sa-accent);border-radius:50%;animation:spin .8s linear infinite;flex:none}',
    '@keyframes spin{to{transform:rotate(360deg)}}',
    'details.steps,details.think{font-size:12.5px;color:var(--sa-muted);border:1px solid var(--sa-border);border-radius:8px;padding:0 10px;background:var(--sa-surface)}',
    'details.steps summary,details.think summary{cursor:pointer;padding:6px 0;list-style:none;display:flex;align-items:center;gap:6px}',
    'details summary::-webkit-details-marker{display:none}',
    'details summary::before{content:"";width:0;height:0;border:4px solid transparent;border-left:5px solid currentColor;margin-right:2px;transition:transform .15s}',
    'details[open]>summary::before{transform:rotate(90deg) translateX(2px)}',
    '.steps ol{margin:0 0 8px;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px}',
    '.steps li{display:flex;gap:6px;align-items:flex-start}',
    '.steps li svg{width:14px;height:14px;flex:none;margin-top:2px;fill:currentColor}',
    '.steps li.ok svg{fill:var(--sa-ok)}.steps li.fail{color:var(--sa-err)}',
    '.steps li .spinner{width:12px;height:12px;margin-top:3px}',
    '.steps .interim{font-style:italic}',
    '.think pre{white-space:pre-wrap;margin:0 0 8px;max-height:12rem;overflow:auto;font:12px/1.45 var(--sa-font)}',
    '.note{font-size:12.5px;border-radius:8px;padding:6px 10px;display:flex;gap:6px;align-items:flex-start}',
    '.note svg{width:15px;height:15px;flex:none;fill:currentColor;margin-top:1px}',
    '.note.warn{background:var(--sa-warn-bg);color:var(--sa-warn)}.note.err{background:var(--sa-err-bg);color:var(--sa-err)}',
    '.note.muted{background:var(--sa-surface);color:var(--sa-muted)}',
    '.note button{margin-left:auto;border:0;background:none;color:inherit;text-decoration:underline;cursor:pointer;font-size:12.5px;white-space:nowrap}',
    '.card{border:1px solid var(--sa-border);border-left:3px solid var(--sa-accent);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:6px;background:var(--sa-bg)}',
    '.card header{padding:0;border:0;display:flex;gap:6px;align-items:center;font-size:12px;color:var(--sa-muted)}',
    '.card header b{color:var(--sa-ink);font-size:13px}',
    '.card .badge{font-size:11px;border-radius:999px;padding:0 7px;background:var(--sa-surface);border:1px solid var(--sa-border)}',
    '.card .badge.empty{background:#e7f3ff;border-color:#b6d4f5}.card .badge.inconsistent{background:var(--sa-warn-bg);border-color:#f0d58a}',
    '.card .badge.improvement{background:#eaf6ec;border-color:#b7dcc0}',
    '.card .where{font-size:11.5px;color:var(--sa-muted)}',
    '.card .current{font-size:12px;color:var(--sa-muted);border-left:2px solid var(--sa-border);padding-left:8px;white-space:pre-wrap;max-height:4.5em;overflow:hidden}',
    '.fill-all{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--sa-muted)}',
    '.card textarea{font:inherit;width:100%;border:1px solid var(--sa-border);border-radius:6px;padding:6px 8px;resize:vertical;min-height:2.6em;color:var(--sa-ink);background:var(--sa-bg)}',
    '.card .why{font-size:12.5px;color:var(--sa-muted)}',
    '.card details.more{display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--sa-muted)}',
    '.card details.more>summary{cursor:pointer;width:fit-content;color:var(--sa-accent);list-style:none;display:flex;align-items:center;gap:6px}',
    '.card details.more[open]>summary{margin-bottom:6px}',
    '.card .actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}',
    '.btn{border:1px solid var(--sa-border);background:var(--sa-bg);border-radius:6px;padding:4px 10px;font-size:13px;cursor:pointer}',
    '.btn:hover{background:var(--sa-surface)}',
    '.btn.primary{background:var(--sa-accent);border-color:var(--sa-accent);color:var(--sa-accent-ink)}',
    '.btn.primary:hover{filter:brightness(1.08)}',
    '.btn:disabled{opacity:.55;cursor:default}',
    '.card .result{font-size:12.5px}.card .result.ok{color:var(--sa-ok)}.card .result.err{color:var(--sa-err)}',
    '.card.dismissed{border-left-color:var(--sa-border);padding:6px 10px;flex-direction:row;align-items:center;font-size:12.5px;color:var(--sa-muted)}',
    '.foot{display:flex;align-items:center;gap:2px;flex-wrap:wrap;font-size:12px;color:var(--sa-muted)}',
    '.foot .icon-btn{width:28px;height:28px}.foot .icon-btn svg{width:16px;height:16px}',
    '.foot .meta{margin-left:4px}',
    '.sources{font-size:12px;color:var(--sa-muted)}.sources summary{cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:4px}',
    '.sources ul{margin:2px 0 4px;padding-left:18px}',
    '.details{font-size:12px;color:var(--sa-muted);border-top:1px dashed var(--sa-border);padding-top:6px}',
    '.details dl{display:grid;grid-template-columns:max-content 1fr;gap:2px 10px;margin:0 0 6px}.details dt{font-weight:500}.details dd{margin:0}',
    '.details pre{white-space:pre-wrap;max-height:14rem;overflow:auto;background:var(--sa-surface);border-radius:6px;padding:6px 8px;font:11.5px/1.45 ui-monospace,Menlo,monospace}',
    '.jump{position:absolute;left:50%;transform:translateX(-50%);bottom:118px;border:1px solid var(--sa-border);background:var(--sa-bg);border-radius:999px;',
    'padding:4px 12px 4px 8px;font-size:12px;display:flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(16,24,40,.12);cursor:pointer}',
    '.jump svg{width:16px;height:16px;fill:currentColor}.jump[hidden]{display:none}',
    '.composer .icon-btn{width:30px;height:30px;flex:none;align-self:flex-end}',
    '.composer .icon-btn svg{width:17px;height:17px}',
    '.composer .icon-btn[hidden]{display:none}',
    '.attach{display:flex;flex-direction:column;gap:6px;margin-bottom:8px;font-size:12px;color:var(--sa-muted)}',
    '.attach[hidden]{display:none}',
    '.attach .doc{display:flex;align-items:center;gap:8px;border:1px solid var(--sa-border);border-radius:10px;padding:6px 4px 6px 10px;background:var(--sa-surface)}',
    '.attach .doc>svg{width:16px;height:16px;flex:none;fill:var(--sa-accent)}',
    '.attach .doc .spinner{width:13px;height:13px}',
    '.attach .doc .name{display:flex;flex-direction:column;min-width:0;flex:1;line-height:1.3}',
    '.attach .doc .name b{font-weight:500;font-size:12.5px;color:var(--sa-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.attach .doc .icon-btn{width:24px;height:24px;flex:none}.attach .doc .icon-btn svg{width:14px;height:14px}',
    '.attach .doc.err{border-color:#f3c2bd;background:var(--sa-err-bg);color:var(--sa-err)}.attach .doc.err>svg{fill:currentColor}',
    '.attach .doc-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}',
    '.attach .pick{display:flex;gap:6px;align-items:center}',
    '.attach select{flex:1;min-width:0;font:inherit;font-size:12px;border:1px solid var(--sa-border);border-radius:6px;padding:3px 4px;background:var(--sa-bg);color:var(--sa-ink)}',
    '.attach .btn{padding:3px 10px;font-size:12px}',
    '.attach .link{border:0;background:none;padding:3px 4px;color:var(--sa-accent);cursor:pointer;font:inherit;font-size:12px}',
    '.attach .link:hover{text-decoration:underline}',
    '.attach .srclist{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px;max-height:132px;overflow:auto}',
    '.attach .srclist[hidden]{display:none}',
    '.attach .src{display:flex;align-items:center;gap:8px;border:1px solid var(--sa-border);border-radius:10px;padding:4px 4px 4px 8px;background:var(--sa-surface)}',
    '.attach .src.bad{border-color:#f3c2bd;background:var(--sa-err-bg);color:var(--sa-err)}',
    '.attach .src input{flex:none;margin:0;cursor:pointer}',
    '.attach .src .name{display:flex;flex-direction:column;min-width:0;flex:1;line-height:1.3}',
    '.attach .src .name b{font-weight:500;font-size:12.5px;color:var(--sa-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.attach .src .icon-btn{width:24px;height:24px;flex:none}.attach .src .icon-btn svg{width:14px;height:14px}',
    '.attach .srcbadge{flex:none;font-size:11px;padding:1px 7px;border-radius:999px;border:1px solid var(--sa-border);color:var(--sa-muted)}',
    '.attach .srcbadge.chat{background:#f1ecfb;border-color:#d4c6f0;color:var(--sa-ink)}',
    '.attach .srchead{display:flex;align-items:center;justify-content:space-between;gap:8px}',
    '.attach .srctoggle{border:0;background:none;padding:2px 0;font:inherit;font-size:12px;font-weight:500;color:var(--sa-ink);cursor:pointer;display:inline-flex;gap:6px;align-items:center}',
    '.attach .srctoggle .chev{display:inline-block;width:10px;color:var(--sa-muted)}',
    '.attach .choice{display:flex;flex-direction:column;gap:6px}',
    '.attach .opt{display:flex;flex-direction:column;align-items:flex-start;gap:1px;text-align:left;width:100%;border:1px solid var(--sa-border);border-radius:10px;padding:6px 10px;background:var(--sa-surface);color:var(--sa-ink);cursor:pointer;font:inherit}',
    '.attach .opt:hover,.attach .opt:focus-visible{border-color:var(--sa-accent)}',
    '.attach .opt.primary{border-color:var(--sa-accent)}',
    '.attach .opt b{font-weight:600;font-size:12.5px}.attach .opt span{font-size:11.5px;color:var(--sa-muted)}',
    '.panel.dropping .composer{border-style:dashed;border-color:var(--sa-accent);background:var(--sa-surface)}',
    '.vf{display:flex;flex-direction:column;gap:8px}',
    '.vf-bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px 8px;font-size:12.5px;color:var(--sa-muted)}',
    '.vf-bar .grow{flex:1}',
    '.vf-tabs{display:flex;gap:4px}',
    '.vf-tabs button{border:1px solid var(--sa-border);background:var(--sa-bg);border-radius:999px;padding:1px 10px;font-size:12px;cursor:pointer;color:var(--sa-ink)}',
    '.vf-tabs button[aria-pressed=true]{background:var(--sa-accent);border-color:var(--sa-accent);color:var(--sa-accent-ink)}',
    '.vf-row{display:grid;grid-template-columns:auto 1fr;gap:2px 8px;border:1px solid var(--sa-border);border-left:3px solid var(--sa-accent);border-radius:8px;padding:7px 8px;background:var(--sa-bg)}',
    '.vf-row.differs{border-left-color:#d9a400}.vf-row.done{border-left-color:var(--sa-ok);opacity:.8}.vf-row.failed{border-left-color:var(--sa-err)}',
    '.vf-row input[type=checkbox]{margin:4px 0 0;width:16px;height:16px;accent-color:var(--sa-accent)}',
    '.vf-head{display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:12px;color:var(--sa-muted)}',
    '.vf-head code{font:600 12.5px ui-monospace,Menlo,monospace;color:var(--sa-ink)}',
    '.vf-row textarea{grid-column:2;font:inherit;width:100%;border:1px solid var(--sa-border);border-radius:6px;padding:4px 7px;resize:vertical;min-height:2em;color:var(--sa-ink);background:var(--sa-bg)}',
    '.card.rows .rowtab{display:grid;gap:6px;margin:6px 0}',
    '.card.rows .rowline{display:flex;gap:6px;align-items:center;flex-wrap:wrap}',
    '.card.rows .rowline input[type=text]{flex:1 1 90px;min-width:0;font:inherit;border:1px solid var(--sa-border);border-radius:6px;padding:4px 7px;color:var(--sa-ink);background:var(--sa-bg)}',
    '.card.rows .rowline input[type=checkbox]{width:16px;height:16px;accent-color:var(--sa-accent)}',
    '.vf-row .sub{grid-column:2;font-size:11.5px;color:var(--sa-muted)}',
    '.vf-row .sub.err{color:var(--sa-err)}.vf-row .sub.ok{color:var(--sa-ok)}',
    '.vf-row details.src{grid-column:2;font-size:11.5px;color:var(--sa-muted)}.vf-row details.src summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px}',
    '.vf-done{font-size:12.5px;display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center}',
    '.card .quote{font-size:12px;color:var(--sa-muted);font-style:italic;border-left:2px solid #b6d4f5;padding-left:8px}',
    '.card .badge.doc{background:#f1ecfb;border-color:#d4c6f0}',
    'form{padding:10px 12px 12px;border-top:1px solid var(--sa-border)}',
    '.composer{display:flex;align-items:flex-end;gap:8px;border:1px solid var(--sa-border);border-radius:12px;padding:6px 6px 6px 12px;background:var(--sa-bg)}',
    '.composer:focus-within{border-color:var(--sa-accent);box-shadow:0 0 0 3px rgba(21,101,192,.15)}',
    'textarea.input:focus-visible{outline:none}',
    'textarea.input{flex:1;border:0;outline:0;resize:none;font:inherit;line-height:1.45;max-height:10.5em;min-height:1.45em;padding:4px 0;background:transparent;color:var(--sa-ink)}',
    '.send{width:34px;height:34px;border:0;border-radius:9px;background:var(--sa-accent);color:var(--sa-accent-ink);display:grid;place-items:center;cursor:pointer;flex:none}',
    '.send svg{width:18px;height:18px;fill:currentColor}',
    '.send:disabled{background:var(--sa-border);cursor:default}',
    '.send.stop{background:var(--sa-ink)}',
    '.hint{display:flex;justify-content:space-between;margin-top:6px;font-size:11.5px;color:var(--sa-muted)}',
    '.hint .over{color:var(--sa-err)}',
    '.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
    '@media (max-width:600px){.panel{width:100vw!important;min-width:0;top:0}.resizer{display:none}}',
    '@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}'
  ].join('');

  // ------------------------------------------------------------------ element

  class ServerChat extends HTMLElement {
    connectedCallback() {
      if (this._root) return;
      this.api = (this.dataset.api || '/').replace(/\/?$/, '/');
      this.recordId = this.dataset.recordId || '';
      this.section = this.dataset.section || '';
      this.fileId = this.dataset.dataFileId || '';
      this.turns = this._restore();
      this.sources = [];
      this._ticks = this._loadTicks();
      this._pendingFile = null;
      this._attachNote = null;
      this._attachError = null;
      this._sourcesError = null;
      this.status = null;
      this.busy = null;
      this.model = null;
      this._root = this.attachShadow({ mode: 'open' });
      this._build();
      this._bindGlobal();
      if (localStorage.getItem(STORE + 'open') === '1') this.open(false);
    }

    // ---------------------------------------------------------------- structure

    _build() {
      var self = this;
      var r = this._root;
      r.appendChild(el('style', { text: CSS }));
      if (this.dataset.launcherOffset) this.style.setProperty('--sa-launcher-offset', this.dataset.launcherOffset);
      var width = parseInt(localStorage.getItem(STORE + 'width') || '', 10);
      if (width) this.style.setProperty('--sa-width', Math.max(340, Math.min(760, width)) + 'px');

      var mac = /Mac|iPhone|iPad/.test(navigator.platform);
      this.launcher = el('button', {
        type: 'button', class: 'launcher', 'aria-expanded': 'false', 'aria-controls': 'sa-panel',
        title: 'Open the assistant (' + (mac ? '⌘' : 'Ctrl') + '+/)', onclick: function () { self.open(true); }
      }, [icon('spark'), 'Assistant']);

      this.contextLine = el('div', { class: 'context' });
      this.panel = el('section', { id: 'sa-panel', class: 'panel', role: 'complementary', 'aria-label': 'Assistant', hidden: true }, [
        this.resizer = el('div', { class: 'resizer', role: 'separator', 'aria-orientation': 'vertical', 'aria-label': 'Resize assistant panel', tabindex: '0' }),
        el('header', {}, [
          el('div', { class: 'title' }, [el('h2', {}, [icon('spark'), 'Assistant']), this.contextLine]),
          iconButton('plus', 'New chat', function () { self.newChat(); }),
          iconButton('close', 'Close assistant (Esc)', function () { self.close(); })
        ]),
        this.modelBar = el('div', { class: 'modelbar' }),
        this.bannerSlot = el('div'),
        this.log = el('div', { class: 'log', role: 'log', 'aria-live': 'off', 'aria-label': 'Conversation', tabindex: '-1' }),
        this.jump = el('button', { type: 'button', class: 'jump', hidden: true, onclick: function () { self._scroll(true); } }, [icon('down'), 'Latest']),
        this.form = el('form', { onsubmit: function (e) { e.preventDefault(); self._submit(); } }, [
          this.attachBar = el('div', { class: 'attach', hidden: true, 'aria-live': 'polite' }),
          el('div', { class: 'composer' }, [
            this.clipBtn = el('button', { type: 'button', class: 'icon-btn', title: 'Add a source (PDF, text or Markdown)', 'aria-label': 'Add a source',
              onclick: function () { self.fileInput.click(); } }, [icon('clip')]),
            this.input = el('textarea', { class: 'input', rows: '1', 'aria-label': 'Message the assistant', maxlength: String(MAX_QUESTION + 500) }),
            this.sendBtn = el('button', { type: 'submit', class: 'send', 'aria-label': 'Send' }, [icon('send')])
          ]),
          this.fileInput = el('input', { type: 'file', accept: '.pdf,.txt,.md,application/pdf,text/plain,text/markdown', hidden: true,
            onchange: function (e) { var f = e.target.files && e.target.files[0]; e.target.value = ''; if (f) self._attach(f); } }),
          this.fileInput,
          el('div', { class: 'hint' }, [el('span', { text: 'Enter to send · Shift+Enter for a new line' }), this.counter = el('span')])
        ]),
        this.announcer = el('div', { class: 'sr', 'aria-live': 'polite', 'aria-atomic': 'true' })
      ]);
      r.appendChild(this.launcher);
      r.appendChild(this.panel);

      this.input.addEventListener('input', function () { self._autosize(); self._updateComposer(); });
      this.input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); self._submit(); }
      });
      // Only the user scrolling up stops the view from following; content growing under a still view
      // (cards, the final render) must not count as leaving the bottom.
      this._lastTop = 0;
      this.log.addEventListener('scroll', function () {
        var top = self.log.scrollTop;
        var atBottom = self.log.scrollHeight - top - self.log.clientHeight < 40;
        if (atBottom) { self._follow = true; self.jump.hidden = true; }
        else if (top < self._lastTop - 4 && Date.now() >= (self._autoScrollUntil || 0)) { self._follow = false; self.jump.hidden = false; }
        self._lastTop = top;
      });
      this._bindResize();
      this._bindDrop();
      this._renderContext();
      this._renderLog();
      this._updateComposer();
    }

    // A document dropped anywhere on the panel is attached like one picked with the paperclip.
    _bindDrop() {
      var self = this;
      var depth = 0;
      var hasFile = function (e) { return self.recordId && e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0; };
      this.panel.addEventListener('dragenter', function (e) { if (!hasFile(e)) return; e.preventDefault(); depth++; self.panel.classList.add('dropping'); });
      this.panel.addEventListener('dragover', function (e) { if (hasFile(e)) e.preventDefault(); });
      this.panel.addEventListener('dragleave', function () { if (depth && --depth === 0) self.panel.classList.remove('dropping'); });
      this.panel.addEventListener('drop', function (e) {
        if (!hasFile(e)) return;
        e.preventDefault();
        depth = 0;
        self.panel.classList.remove('dropping');
        var f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) self._attach(f);
      });
    }

    _bindGlobal() {
      var self = this;
      document.addEventListener('pslm-route-change', function (e) {
        var d = e.detail || {};
        self.section = d.section || '';
        self.fileId = d.fileId || '';
        self._renderContext();
        self._renderAttachBar();
        if (!self.panel.hidden) self._loadSources();
        if (!self.turns.length) self._renderLog();
      });
      document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === '/') {
          e.preventDefault();
          self.panel.hidden ? self.open(true) : self.close();
        }
      });
      this.panel.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (self.busy) { self.stop(); e.stopPropagation(); return; }
        self.close();
      });
    }

    _bindResize() {
      var self = this;
      var setWidth = function (w) {
        w = Math.max(340, Math.min(Math.min(760, window.innerWidth), Math.round(w)));
        self.style.setProperty('--sa-width', w + 'px');
        self.resizer.setAttribute('aria-valuenow', String(w));
        localStorage.setItem(STORE + 'width', String(w));
      };
      this.resizer.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        self.resizer.setPointerCapture(e.pointerId);
        var move = function (ev) { setWidth(window.innerWidth - ev.clientX); };
        var up = function () {
          self.resizer.removeEventListener('pointermove', move);
          self.resizer.removeEventListener('pointerup', up);
        };
        self.resizer.addEventListener('pointermove', move);
        self.resizer.addEventListener('pointerup', up);
      });
      this.resizer.addEventListener('keydown', function (e) {
        var current = self.panel.getBoundingClientRect().width;
        if (e.key === 'ArrowLeft') { setWidth(current + 24); e.preventDefault(); }
        if (e.key === 'ArrowRight') { setWidth(current - 24); e.preventDefault(); }
      });
    }

    // ---------------------------------------------------------------- open / close / status

    // Sit below the host's top bar (the editor's app bar holds the user menu) instead of over it. The
    // bar is found by what it is (a header, nav or app bar at the top edge), not by its positioning:
    // the editor's Vuetify app bar is position:relative.
    _measureTop() {
      if (this.dataset.top) return this.dataset.top;
      var self = this;
      var hits = document.elementsFromPoint(window.innerWidth - 60, 4).filter(function (n) { return n !== self; });
      for (var i = 0; i < hits.length; i++) {
        var n = hits[i];
        if (/^(HEADER|NAV)$/.test(n.tagName) || /\b(v-app-bar|navbar|main-header)\b/.test(String(n.className))) {
          var bottom = Math.round(n.getBoundingClientRect().bottom);
          return bottom > 0 && bottom <= 140 ? bottom + 'px' : '0px';
        }
      }
      return '0px';
    }

    open(focus) {
      this.style.setProperty('--sa-top', this._measureTop());
      this.panel.hidden = false;
      this.launcher.setAttribute('aria-expanded', 'true');
      localStorage.setItem(STORE + 'open', '1');
      if (!this.status) this._loadStatus();
      this._loadSources();
      this._scroll(true);
      if (focus) this.input.focus();
    }

    close() {
      this.panel.hidden = true;
      this.launcher.setAttribute('aria-expanded', 'false');
      localStorage.setItem(STORE + 'open', '0');
      clearTimeout(this._srcTimer);
      this._srcTimer = null;
      this.launcher.focus();
    }

    _loadStatus() {
      var self = this;
      var url = this.api + 'index.php/api/assistant/status' + (this.recordId ? '?sid=' + encodeURIComponent(this.recordId) : '');
      this._statusError = null;
      this._renderModelBar();
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.body && r.body.message || 'Could not reach the assistant.');
          self.status = r.body;
          var models = (r.body.models || []);
          var saved = localStorage.getItem(STORE + 'model');
          var usable = models.filter(function (m) { return m.available !== false; });
          var pick = models.filter(function (m) { return m.alias === saved && m.available !== false; })[0]
            || models.filter(function (m) { return m.default && m.available !== false; })[0] || usable[0] || models[0];
          self.model = pick ? pick.alias : null;
          self._renderContext();
          self._renderModelBar();
          self._renderLog();
          self._renderAttachBar();
          self._resumeReviews();
          self._updateComposer();
        })
        .catch(function (err) {
          self._statusError = err.message;
          self._renderModelBar();
          self._updateComposer();
        });
    }

    _project() { return this.status && this.status.project; }
    _canEdit() { return !!(this.status && this.status.can_edit); }
    _fields() { return (this.status && this.status.fields) || {}; }
    // Pointer -> {label, section, fillable, required}. Older services and the config fallback send a
    // bare label string.
    _field(pointer) {
      var f = this._fields()[pointer];
      if (f && typeof f === 'object') return f;
      return { label: typeof f === 'string' && f ? f : pointer.split('/').pop(), section: '', fillable: true };
    }
    _modelInfo() {
      var self = this;
      return ((this.status && this.status.models) || []).filter(function (m) { return m.alias === self.model; })[0] || null;
    }

    _renderContext() {
      var p = this._project();
      var bits = [];
      if (this.recordId) bits.push(p && p.title ? p.title : 'Project ' + this.recordId);
      else bits.push('Editor help');
      if (this.section) bits.push(this.section);
      this.contextLine.textContent = '';
      this.contextLine.appendChild(el('span', { text: bits[0] }));
      if (bits[1]) this.contextLine.appendChild(el('span', { class: 'where', text: 'Viewing: ' + bits[1] }));
      this.contextLine.title = 'The assistant answers about: ' + bits.join(' · ');
    }

    _renderModelBar() {
      var self = this;
      var bar = this.modelBar;
      bar.textContent = '';
      this.bannerSlot.textContent = '';
      if (this._statusError) {
        this.bannerSlot.appendChild(el('div', { class: 'banner err', role: 'alert' }, [icon('warn'),
          el('span', { text: this._statusError + ' ' }),
          el('button', { type: 'button', class: 'btn', onclick: function () { self._loadStatus(); } }, ['Try again'])]));
        return;
      }
      if (!this.status) { bar.appendChild(el('span', { class: 'working' }, [el('span', { class: 'spinner' }), 'Connecting…'])); return; }
      if (!this.status.enabled) {
        this.bannerSlot.appendChild(el('div', { class: 'banner err', role: 'alert' }, [icon('warn'), el('span', { text: 'The server assistant is turned off for this editor.' })]));
        return;
      }
      var models = this.status.models || [];
      if (!models.length) {
        var why = this.status.keep_local
          ? 'This project is set to stay on this server, and no local model is available. ' + (this.status.can_admin ? 'You can change that setting below.' : 'Ask a project administrator to change the setting.')
          : (this.status.service && (this.status.service.error || this.status.service.detail)) || 'No model is configured on the assistant service.';
        this.bannerSlot.appendChild(el('div', { class: 'banner err', role: 'alert' }, [icon('warn'), el('span', { text: why + ' ' }),
          el('button', { type: 'button', class: 'btn', onclick: function () { self.status = null; self._loadStatus(); } }, ['Try again'])]));
        if (this.status.keep_local) this._keepLocalControl(bar);
        return;
      }
      var info = this._modelInfo();
      bar.appendChild(el('span', { class: 'dot' + (info && info.available === false ? ' off' : ''), 'aria-hidden': 'true' }));
      if (models.length > 1) {
        var select = el('select', { 'aria-label': 'Model', onchange: function () {
          self.model = select.value;
          localStorage.setItem(STORE + 'model', self.model);
          self._renderModelBar();
          self._updateComposer();
        } });
        models.forEach(function (m) {
          var opt = el('option', { value: m.alias, text: m.label + (m.available === false ? ' (offline)' : '') });
          if (m.available === false) opt.disabled = true;
          if (m.alias === self.model) opt.selected = true;
          select.appendChild(opt);
        });
        bar.appendChild(select);
      } else {
        bar.appendChild(el('span', { text: info ? info.label : models[0].label }));
      }
      if (info) {
        var external = !info.local && this.recordId;
        bar.appendChild(el('span', { class: 'tag' + (external ? ' ext' : ''), tabindex: external ? '0' : null,
          text: info.local ? (this.status.keep_local ? 'Kept on this server' : 'Runs on your server') : (external ? 'Data sent externally' : 'External provider'),
          title: external ? 'Questions and the project data needed to answer them are sent to ' + info.label + ', an external service.' : '' }));
      }
      if (info && info.available === false) {
        this.bannerSlot.appendChild(el('div', { class: 'banner err', role: 'alert' }, [icon('warn'),
          el('span', { text: info.label + ' is not reachable right now. ' }),
          el('button', { type: 'button', class: 'btn', onclick: function () { self.status = null; self._loadStatus(); } }, ['Check again'])]));
      }
      this._keepLocalControl(bar);
    }

    // Project administrators can keep a project's data on this server: only a local model then answers,
    // and nothing from the project is sent to a hosted provider. Off by default.
    _keepLocalControl(bar) {
      var self = this;
      if (!this.recordId || !this.status || !this.status.can_admin) return;
      var box = el('input', { type: 'checkbox', class: 'keeplocal', 'aria-label': 'Keep this project on this server' });
      box.checked = !!this.status.keep_local;
      box.addEventListener('change', function () {
        var want = box.checked;
        box.disabled = true;
        fetch(self.api + 'index.php/api/assistant/settings', {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ sid: self.recordId, keep_local: want })
        }).then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
          .then(function (r) {
            if (!r.ok || !r.body || r.body.status !== 'success') throw new Error((r.body && r.body.message) || 'The setting could not be saved.');
            self.status = null;
            self._loadStatus();
          })
          .catch(function (err) { box.checked = !want; box.disabled = false; self._announce(err.message); });
      });
      bar.appendChild(el('label', { class: 'keep', title: 'When on, only a model running on this server answers about this project. Nothing from it is sent to an external provider. Only project administrators can change this.' }, [box, ' Keep on this server']));
    }

    // ---------------------------------------------------------------- conversation

    _storeKey() { return STORE + 'chat:' + (this.recordId || 'app'); }

    _restore() {
      try {
        var saved = JSON.parse(sessionStorage.getItem(STORE + 'chat:' + (this.dataset.recordId || 'app')) || '[]');
        return Array.isArray(saved) ? saved.filter(function (t) { return t && t.question; }) : [];
      } catch (e) { return []; }
    }

    _persist() {
      var self = this;
      // A streaming turn is kept only while a job runs for it: that is what lets a reload resume polling.
      var keep = this.turns.filter(function (t) { return t.status !== 'streaming' || t.jobId; }).slice(-30).map(function (t) {
        return { question: t.question, kind: t.kind, action: t.action, text: t.text, status: t.status, error: t.error, errorDetail: t.errorDetail, steps: t.steps, proposals: t.proposals,
          attrs: t.attrs, varfill: self._keepVarfill(t.varfill),
          citations: t.citations, warnings: t.warnings, meta: t.meta, jobId: t.jobId || null, done: t.done ? { timings: t.done.timings, usage: t.done.usage, model: t.done.model, served_by: t.done.served_by || null, sent: t.done.sent } : null };
      });
      try { sessionStorage.setItem(this._storeKey(), JSON.stringify(keep)); } catch (e) { /* storage full: keep in memory */ }
    }

    // A long variable list is kept across a reload only while it is small enough for session storage.
    _keepVarfill(vf) {
      if (!vf) return null;
      var size = JSON.stringify(vf).length;
      return size <= 250000 ? vf : { fileId: vf.fileId, fileName: vf.fileName, stats: vf.stats, items: [], dropped: true, undo: vf.undo || [], applied: vf.applied || 0, filter: 'all', limit: 60 };
    }

    newChat() {
      if (this.busy) this.stop();
      this.turns = [];
      this._persist();
      this._renderLog();
      this.input.focus();
      this._announce('New chat started.');
    }

    _suggestions() {
      var p = this._project();
      if (!this.recordId) {
        return [
          ['How do I create a new project?', 'How do I create a new project?'],
          ['Import a DDI file', 'How do I import an existing DDI XML file into a project?'],
          ['Share a project', 'How do I share a project with a collaborator?']
        ];
      }
      if (this._hasSources() && !this.fileId) {
        return [
          ['Fill fields from ' + this._sourcesLabel(), '', 'docfill'],
          ['What do the sources cover?', 'Summarize what my ticked sources cover in a few bullet points: topic, coverage, period, and who produced them.'],
          ['Review this record', '', 'review']
        ];
      }
      if (this.fileId) {
        return [
          ['Draft a description for this data file', 'Look at the open data file and its variables, then propose a description for it.'],
          ['Variables missing labels', 'Which variables in the open data file have no label? List them.'],
          ['Summarize the project', 'Summarize this project in three sentences.']
        ];
      }
      var indicator = p && /indicator|timeseries/i.test(p.type || '');
      return [
        ['Review this record', 'Review this record: find required and recommended fields that are empty, unclear or inconsistent, propose values where the project\'s own content supports them, and summarize what you found.', 'review'],
        ['Which required fields are empty?', 'Which required and recommended fields of this project are empty? List them by section.'],
        indicator ? ['Improve the definition', 'Review the indicator\'s short and long definitions and propose an improvement if they are unclear or inconsistent.']
          : ['Suggest a universe', 'Read the abstract and propose a value for the Universe field if it is empty or vague.']
      ];
    }

    _renderLog() {
      var self = this;
      this.log.textContent = '';
      if (!this.turns.length) {
        var where = this.recordId ? 'this project' : 'the Metadata Editor';
        var empty = el('div', { class: 'empty' }, [
          el('h3', { text: 'How can I help?' }),
          el('p', { text: 'Ask about ' + where + ', its fields, or how to use the editor. I can read saved fields and suggest values; you decide what goes into the form.' }),
          el('div', { class: 'suggestions' }, this._suggestions().map(function (s) {
            return el('button', { type: 'button', class: 'suggestion', onclick: function () {
              if (s[2] === 'review') self.review(); else if (s[2] === 'docfill') self.fillFromDocument(); else self.ask(s[1]);
            } }, [s[0]]);
          }))
        ]);
        this.log.appendChild(empty);
        return;
      }
      this.turns.forEach(function (t) { self.log.appendChild(self._renderTurn(t)); });
      this._scroll(true);
    }

    _renderTurn(t) {
      var node = el('article', { class: 'turn' });
      t.node = node;
      node.appendChild(el('div', { class: 'q' }, [el('span', { class: 'sr', text: 'You: ' }), t.question]));
      var a = el('div', { class: 'a' }, [el('span', { class: 'sr', text: 'Assistant: ' })]);
      t.parts = {
        a: a,
        think: null,
        steps: null,
        answer: el('div', { class: 'answer' }),
        notes: el('div', { style: 'display:flex;flex-direction:column;gap:6px;margin-top:6px' }),
        cards: el('div', { style: 'display:flex;flex-direction:column;gap:8px;margin-top:8px' }),
        foot: el('div')
      };
      a.appendChild(t.parts.answer);
      a.appendChild(t.parts.cards);
      a.appendChild(t.parts.notes);
      a.appendChild(t.parts.foot);
      node.appendChild(a);
      this._paintSteps(t);
      this._paintAnswer(t);
      this._repaintCards(t);
      this._paintNotes(t);
      this._paintFoot(t);
      return node;
    }

    _paintAnswer(t) {
      var box = t.parts.answer;
      box.textContent = '';
      t.node.classList.toggle('streaming', t.status === 'streaming' && !!t.text);
      if (t.text) { box.appendChild(renderMarkdown(t.text)); return; }
      if (t.status === 'streaming') {
        box.appendChild(el('div', { class: 'working' }, [el('span', { class: 'spinner', 'aria-hidden': 'true' }), t.phase || 'Thinking…']));
      }
    }

    _stepLabel(s) {
      var self = this;
      var args = s.args || {};
      var field = args.pointer ? this._field(args.pointer).label : '';
      var many = function (list) {
        var names = (list || []).map(function (ptr) { return '“' + self._field(ptr).label + '”'; });
        return names.length > 3 ? names.slice(0, 3).join(', ') + ' and ' + (names.length - 3) + ' more' : names.join(', ');
      };
      var filters = { empty: 'empty fields', required: 'required fields', recommended: 'required and recommended fields', filled: 'filled fields' };
      switch (s.name) {
        case 'list_fields': return 'Listed ' + (filters[args.filter] || 'the fields') + (args.query ? ' matching “' + args.query + '”' : '');
        case 'read_fields': return 'Read ' + many(args.pointers);
        case 'read_project_field': return 'Read the saved “' + field + '”';
        case 'get_current_page': return 'Looked at the current page';
        case 'list_data_files': return 'Listed the data files';
        case 'find_variables': return args.missing ? 'Found variables missing a ' + args.missing : 'Searched variables for “' + (args.query || '') + '”';
        case 'search_docs': return 'Searched the documentation for “' + (args.query || '') + '”';
        case 'search_document': return 'Searched the attached document for “' + (args.query || '') + '”';
        case 'read_codebook': return 'Read the variable lines of the codebook';
        case 'label_variables': return 'Read the document for ' + (args.names || []).slice(0, 3).join(', ') + ((args.names || []).length > 3 ? ' and more' : '');
        case 'fill_lists_from_document': return 'Looked for rows for ' + many(args.pointers) + ' in the document';
        case 'fill_from_document': return 'Checked ' + many(args.pointers) + ' against the document';
        case 'propose_field_value': return 'Drafted a value for “' + field + '”';
        case 'propose_datafile_description': return 'Drafted a data file description';
        case 'calculate': return 'Calculated ' + (args.expression || '');
        case 'get_datetime': return 'Checked today’s date';
        default: return 'Used ' + s.name;
      }
    }

    _paintSteps(t) {
      var self = this;
      var steps = t.steps || [];
      var hasThink = !!t.reasoning;
      if (!steps.length && !hasThink) {
        if (t.parts.steps) { t.parts.steps.remove(); t.parts.steps = null; }
        if (t.parts.think) { t.parts.think.remove(); t.parts.think = null; }
        return;
      }
      if (steps.length) {
        if (!t.parts.steps) {
          // Never opened or closed for the user: the summary already names the step in progress.
          t.parts.steps = el('details', { class: 'steps' });
          t.parts.a.insertBefore(t.parts.steps, t.parts.answer);
        }
        var d = t.parts.steps;
        var open = d.open;
        d.textContent = '';
        var running = steps.some(function (s) { return s.state === 'running'; });
        var count = steps.filter(function (s) { return s.kind === 'tool'; }).length;
        var current = running ? steps.filter(function (s) { return s.state === 'running'; }).pop() : null;
        var summary = current ? self._stepLabel(current) + '…' : (count ? 'Tools (' + count + ')' : 'Steps');
        d.appendChild(el('summary', {}, [running ? el('span', { class: 'spinner', 'aria-hidden': 'true' }) : null, summary]));
        d.appendChild(el('ol', {}, steps.map(function (s) {
          if (s.kind === 'interim') return el('li', { class: 'interim' }, [icon('info'), el('span', { text: s.text })]);
          var mark = s.state === 'running' ? el('span', { class: 'spinner', 'aria-hidden': 'true' }) : icon(s.state === 'ok' ? 'check' : 'x');
          return el('li', { class: s.state === 'fail' ? 'fail' : (s.state === 'ok' ? 'ok' : '') }, [mark,
            el('span', { text: self._stepLabel(s) + (s.message ? ' (' + s.message + ')' : '') })]);
        })));
        d.open = open;
      }
      if (hasThink) {
        if (!t.parts.think) {
          t.parts.think = el('details', { class: 'think' }, [el('summary', { text: 'Reasoning' }), el('pre')]);
          t.parts.a.insertBefore(t.parts.think, t.parts.steps || t.parts.answer);
        }
        t.parts.think.querySelector('pre').textContent = t.reasoning.length > 6000 ? '…' + t.reasoning.slice(-6000) : t.reasoning;
      }
    }

    _paintNotes(t) {
      var self = this;
      var box = t.parts.notes;
      box.textContent = '';
      (t.warnings || []).forEach(function (w) {
        box.appendChild(el('div', { class: 'note ' + (w.tone || 'warn') }, [icon(w.tone === 'muted' ? 'info' : 'warn'), el('span', { text: w.text }),
          w.action === 'continue' ? el('button', { type: 'button', onclick: function () { self.ask('Continue.'); } }, ['Continue']) : null]));
      });
      if (t.status === 'stopped') box.appendChild(el('div', { class: 'note muted' }, [icon('info'), el('span', { text: 'Stopped.' })]));
      if (t.status === 'error') {
        box.appendChild(el('div', { class: 'note err', role: 'alert' }, [icon('warn'), el('span', { text: t.error || 'Something went wrong.' }),
          el('button', { type: 'button', onclick: function () { self.retry(t); } }, ['Retry'])]));
        if (t.errorDetail) box.appendChild(el('details', { class: 'sources' }, [el('summary', { text: 'Details' }), el('pre', { style: 'white-space:pre-wrap;margin:4px 0 0;font-size:11.5px', text: t.errorDetail })]));
      }
    }

    _paintFoot(t) {
      var self = this;
      var foot = t.parts.foot;
      foot.textContent = '';
      if (t.status === 'streaming' || t.status === 'error') return;
      var cites = t.citations || [];
      if (cites.length) {
        // Retrieved, not necessarily used: offered as reading rather than claimed as the source. A label
        // from the curator's own sources reads "name p.N"; the editor's documentation labels do not.
        var names = this.sources.map(function (s) { return String(s.name || '').toLowerCase(); }).filter(Boolean);
        var mine = [];
        var docs = [];
        cites.forEach(function (c) {
          var low = String(c).toLowerCase();
          var own = /\sp\.?\s?\d+(\s*[-–]\s*\d+)?\s*$/.test(c) || names.some(function (n) { return low.indexOf(n) === 0; });
          (own ? mine : docs).push(c);
        });
        var list = function (title, items) {
          foot.appendChild(el('details', { class: 'sources' }, [
            el('summary', { text: title + ' (' + Math.min(5, items.length) + ')' }),
            el('ul', {}, items.slice(0, 5).map(function (c) { return el('li', { text: c }); }))]));
        };
        if (mine.length) list('From your sources', mine);
        if (docs.length) list('Related documentation', docs);
      }
      var details = el('div', { class: 'details', hidden: true });
      var row = el('div', { class: 'foot' }, [
        t.text ? iconButton('copy', 'Copy answer', function (e) {
          var btn = e.currentTarget;
          copyText(t.text).then(function () { btn.title = 'Copied'; self._announce('Answer copied.'); }, function () { self._announce('Copy failed.'); });
        }) : null,
        iconButton('retry', 'Ask again', function () { self.retry(t); }),
        iconButton('info', 'Answer details', function (e) {
          details.hidden = !details.hidden;
          e.currentTarget.setAttribute('aria-expanded', String(!details.hidden));
        }),
        el('span', { class: 'meta', text: this._footMeta(t) })
      ]);
      foot.appendChild(row);
      var done = t.done || {};
      var timings = done.timings || {};
      var usage = done.usage || {};
      var dl = el('dl');
      var add = function (k, v) { if (v !== null && v !== undefined && v !== '') { dl.appendChild(el('dt', { text: k })); dl.appendChild(el('dd', { text: String(v) })); } };
      add('Model', t.meta ? t.meta.label + (t.meta.local ? ' (on your server)' : ' (external)') : '');
      add('Time', timings.total_ms ? (timings.total_ms / 1000).toFixed(1) + ' s' : '');
      add('First words after', timings.first_token_ms ? (timings.first_token_ms / 1000).toFixed(1) + ' s' : '');
      add('Speed', timings.tokens_per_sec ? timings.tokens_per_sec + ' tokens/s' : '');
      add('Tokens', usage.completion_tokens ? usage.completion_tokens + ' generated, ' + (usage.peak_prompt_tokens || usage.prompt_tokens || '?') + ' read' : '');
      details.appendChild(dl);
      if (done.sent) {
        details.appendChild(el('details', {}, [el('summary', { text: 'What was sent to the model' }),
          el('pre', { text: '[Instructions]\n' + (done.sent.system || '') + '\n\n[Context]\n' + (done.sent.context || '') })]));
      }
      foot.appendChild(details);
    }

    _footMeta(t) {
      var timings = (t.done && t.done.timings) || {};
      var bits = [];
      var other = t.done && t.done.served_by;
      // A configured fallback answered instead of the model that was chosen: say which one did.
      if (other && other.label) bits.push('Answered by ' + other.label + (other.local ? ' (fallback, ran on your server)' : ' (fallback)'));
      else if (t.meta && t.meta.label) bits.push(t.meta.label);
      if (timings.total_ms) bits.push((timings.total_ms / 1000).toFixed(1) + ' s');
      return bits.join(' · ');
    }

    // A list field: the rows the document names, each with a tick and editable cells; "Add" puts the
    // ticked ones after the saved rows in the form. Nothing is saved.
    _rowsCard(t, p) {
      var self = this;
      var card = el('div', { class: 'card rows', role: 'group', 'aria-label': 'Proposed rows for ' + (p.label || p.pointer) });
      var cols = p.columns || [];
      var chosen = function () { return self._rowsOf(p); };
      var paint = function () {
        card.textContent = '';
        card.className = 'card rows' + (p.state === 'dismissed' ? ' dismissed' : '');
        if (p.state === 'dismissed') {
          card.appendChild(el('span', { text: 'Rows for “' + (p.label || p.pointer) + '” dismissed.' }));
          card.appendChild(el('button', { type: 'button', class: 'btn', style: 'margin-left:auto', onclick: function () { p.state = null; self._persist(); paint(); } }, ['Undo']));
          return;
        }
        card.appendChild(el('header', {}, [el('b', { text: p.label || p.pointer }), el('span', { class: 'badge', text: 'Add rows' }),
          p.source === 'document' ? el('span', { class: 'badge doc', text: 'From document' }) : null]));
        if (p.section) card.appendChild(el('div', { class: 'where', text: p.section }));
        if (p.current) card.appendChild(el('div', { class: 'current', text: p.current + '. These rows are added after them; nothing saved is changed.' }));
        var addBtn = el('button', { type: 'button', class: 'btn primary' });
        var result = el('span', { class: 'result', role: 'status' });
        var refresh = function () {
          var n = chosen().length;
          addBtn.textContent = 'Add ' + n + (n === 1 ? ' row' : ' rows');
          addBtn.disabled = !n || !self._canEdit();
        };
        var table = el('div', { class: 'rowtab' });
        (p.rows || []).forEach(function (r, i) {
          var cb = el('input', { type: 'checkbox', 'aria-label': 'Add row ' + (i + 1) });
          cb.checked = !(p.skip && p.skip[i]);
          cb.addEventListener('change', function () { p.skip = p.skip || {}; p.skip[i] = !cb.checked; refresh(); self._persist(); });
          var cells = cols.map(function (c) {
            var inp = el('input', { type: 'text', placeholder: c.title || c.key, 'aria-label': (c.title || c.key) + ', row ' + (i + 1) });
            inp.value = r[c.key] == null ? '' : r[c.key];
            inp.addEventListener('input', function () { r[c.key] = inp.value; refresh(); self._persist(); });
            return inp;
          });
          table.appendChild(el('div', { class: 'rowline' }, [cb].concat(cells)));
        });
        card.appendChild(table);
        if (p.reason) card.appendChild(el('div', { class: 'why', text: p.reason }));
        if (p.evidence) card.appendChild(el('div', { class: 'quote', title: 'Words from the document', text: '“' + p.evidence + '”' + (p.cite ? ' — ' + p.cite : '') }));
        addBtn.addEventListener('click', function () {
          addBtn.disabled = true;
          result.className = 'result';
          result.textContent = 'Adding…';
          self._fill(p, chosen()).then(function (msg) {
            p.state = 'filled';
            result.className = 'result ok';
            result.textContent = msg;
          }, function (err) {
            result.className = 'result err';
            result.textContent = err.message;
          }).then(function () { refresh(); self._persist(); });
        });
        refresh();
        card.appendChild(el('div', { class: 'actions' }, [self._canEdit() ? addBtn : null,
          el('button', { type: 'button', class: 'btn', onclick: function () { p.state = 'dismissed'; self._persist(); paint(); } }, ['Dismiss']), result]));
        if (!self._canEdit()) result.textContent = 'You can view this project but not edit it.';
        if (p.state === 'filled' && !result.textContent) { result.className = 'result ok'; result.textContent = 'Added. Not saved yet.'; }
        if (p.result && p.state !== 'filled') { result.className = 'result err'; result.textContent = p.result; }
      };
      paint();
      return card;
    }

    _card(t, p) {
      if (p.kind === 'rows') return this._rowsCard(t, p);
      var self = this;
      var card = el('div', { class: 'card', role: 'group', 'aria-label': 'Proposed value for ' + (p.label || p.pointer) });
      var paint = function () {
        card.textContent = '';
        card.className = 'card' + (p.state === 'dismissed' ? ' dismissed' : '');
        if (p.state === 'dismissed') {
          card.appendChild(el('span', { text: 'Proposal for “' + (p.label || p.pointer) + '” dismissed.' }));
          card.appendChild(el('button', { type: 'button', class: 'btn', style: 'margin-left:auto', onclick: function () { p.state = null; self._persist(); paint(); } }, ['Undo']));
          return;
        }
        var kind = p.status && STATUS_LABEL[p.status] ? p.status : null;
        card.appendChild(el('header', {}, [el('b', { text: p.label || p.pointer }),
          el('span', { class: 'badge' + (kind ? ' ' + kind : ''), text: kind ? STATUS_LABEL[kind] : 'Suggestion' })]));
        if (p.section) card.appendChild(el('div', { class: 'where', text: p.section }));
        var current = typeof p.current === 'string' ? p.current : (p.current ? JSON.stringify(p.current) : '');
        var box = el('textarea', { 'aria-label': 'Proposed value (you can edit it)', rows: String(Math.min(8, Math.max(2, Math.ceil((p.value || '').length / 48)))) });
        box.value = p.edited !== undefined ? p.edited : (p.value || '');
        box.addEventListener('input', function () { p.edited = box.value; self._persist(); });
        card.appendChild(box);
        // Everything that explains the value folds away; the label says when it would replace a saved one.
        var hasCurrent = !!(current && current.trim());
        var more = [];
        if (hasCurrent) more.push(el('div', { class: 'current', title: 'Saved now', text: 'Saved now: ' + current }));
        if (p.reason) more.push(el('div', { class: 'why', text: p.reason }));
        if (p.evidence) more.push(el('div', { class: 'quote', title: 'Words from the document', text: '“' + p.evidence + '”' + (p.cite ? ' — ' + p.cite : '') }));
        if (p.source === 'document') more.push(el('div', { class: 'where', text: 'Drafted from the attached document.' }));
        if (more.length) card.appendChild(el('details', { class: 'more' }, [el('summary', { text: hasCurrent ? 'Details · replaces a saved value' : 'Details' })].concat(more)));
        var result = el('span', { class: 'result', role: 'status' });
        var fill = self._canEdit() ? el('button', { type: 'button', class: 'btn primary', onclick: function () {
          fill.disabled = true;
          result.className = 'result';
          result.textContent = 'Filling…';
          self._fill(p, box.value).then(function (msg) {
            p.state = 'filled';
            result.className = 'result ok';
            result.textContent = msg || 'Typed into the form. Not saved yet: review it, then Save.';
          }, function (err) {
            result.className = 'result err';
            result.textContent = err.message;
          }).then(function () { fill.disabled = false; self._persist(); });
        } }, ['Fill in form']) : null;
        var actions = el('div', { class: 'actions' }, [fill,
          el('button', { type: 'button', class: 'btn', onclick: function () {
            copyText(box.value).then(function () { result.className = 'result ok'; result.textContent = 'Copied.'; },
              function () { result.className = 'result err'; result.textContent = 'Could not copy; select the text instead.'; });
          } }, ['Copy']),
          el('button', { type: 'button', class: 'btn', onclick: function () { p.state = 'dismissed'; self._persist(); paint(); } }, ['Dismiss']),
          result]);
        if (!self._canEdit()) result.textContent = 'You can view this project but not edit it.';
        if (p.state === 'filled' && !result.textContent) { result.className = 'result ok'; result.textContent = 'Filled. Not saved yet.'; }
        if (p.result && p.state !== 'filled') { result.className = 'result err'; result.textContent = p.result; }
        card.appendChild(actions);
      };
      paint();
      return card;
    }

    // The form bridge (server-assistant-fill.js) answers through respond(); no answer means the
    // editor form is not on this page.
    _fill(p, value) {
      var self = this;
      if (p.kind === 'datafile') return this._fillDatafile(p, value);
      return new Promise(function (resolve, reject) {
        var settled = false;
        var timer = setTimeout(function () {
          if (!settled) { settled = true; reject(new Error('The editor form did not respond. Open the project editor, or use Copy.')); }
        }, 5000);
        var detail = {
          recordId: self.recordId, pointer: p.pointer, label: p.label || p.pointer, suggestion: value,
          respond: function (ok, message) {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            ok ? resolve(message) : reject(new Error(message || 'The form did not accept the value.'));
          }
        };
        self.dispatchEvent(new CustomEvent('assistant-fill-request', { bubbles: true, composed: true, detail: detail }));
      });
    }

    // The data file form owns its description (vue-datafile-edit-component.js listens for
    // pslm-datafile-fill and answers synchronously through accept/deny).
    _fillDatafile(p, value) {
      var self = this;
      return new Promise(function (resolve, reject) {
        if (self.fileId && p.file_id && String(self.fileId) !== String(p.file_id)) {
          return reject(new Error('Open that data file first, or use Copy.'));
        }
        var answered = false;
        var detail = {
          fileId: p.file_id, suggestion: value,
          accept: function () { answered = true; resolve('Typed into the data file description. Not saved yet: review it, then Save.'); },
          deny: function (reason) { answered = true; reject(new Error(reason || 'The data file form did not accept it.')); }
        };
        document.dispatchEvent(new CustomEvent('pslm-datafile-fill', { detail: detail }));
        if (!answered) reject(new Error('Open the data file page to fill its description, or use Copy.'));
      });
    }

    // Several proposals from one answer (a review): fill them one after another, each through the same
    // path as its own button, and report how many the form took.
    _rowsOf(p) {
      var cols = p.columns || [];
      return (p.rows || []).filter(function (r, i) { return !(p.skip && p.skip[i]); }).map(function (r) {
        var out = {};
        cols.forEach(function (c) { var v = String(r[c.key] == null ? '' : r[c.key]).trim(); if (v) out[c.key] = v; });
        return out;
      }).filter(function (r) { return Object.keys(r).length; });
    }

    _fillAll(t, button, status) {
      var self = this;
      var pending = (t.proposals || []).filter(function (p) { return p.state !== 'dismissed' && p.state !== 'filled'; });
      button.disabled = true;
      var ok = 0;
      var failed = 0;
      var next = function (i) {
        if (i >= pending.length) {
          status.textContent = ok + ' filled' + (failed ? ', ' + failed + ' not filled (see the cards)' : '') + '. Not saved yet: review, then Save.';
          button.disabled = false;
          self._persist();
          self._repaintCards(t);
          return;
        }
        var p = pending[i];
        status.textContent = 'Filling ' + (i + 1) + ' of ' + pending.length + '…';
        self._fill(p, p.kind === 'rows' ? self._rowsOf(p) : (p.edited !== undefined ? p.edited : p.value)).then(function () { p.state = 'filled'; p.result = null; ok++; },
          function (err) { p.result = err.message; failed++; }).then(function () { next(i + 1); });
      };
      next(0);
    }

    _repaintCards(t) {
      var self = this;
      t.parts.cards.textContent = '';
      if (t.varfill) return this._paintVarfill(t);
      var fillable = (t.proposals || []).filter(function (p) { return p.state !== 'dismissed'; });
      if (fillable.length > 1 && this._canEdit() && t.status !== 'streaming') {
        var status = el('span', { role: 'status' });
        var btn = el('button', { type: 'button', class: 'btn primary', onclick: function () { self._fillAll(t, btn, status); } }, ['Fill all ' + fillable.length]);
        t.parts.cards.appendChild(el('div', { class: 'fill-all' }, [btn, status]));
      }
      (t.proposals || []).forEach(function (p) { t.parts.cards.appendChild(self._card(t, p)); });
    }

    // ---------------------------------------------------------------- variable list

    _paintVarfill(t) {
      var self = this;
      var vf = t.varfill;
      var box = t.parts.cards;
      box.textContent = '';
      if (vf.dropped) {
        box.appendChild(el('div', { class: 'note' }, [icon('info'), el('span', { text: 'This list was too large to keep after a reload. Run the labelling again to review it.' })]));
        if (vf.applied || (vf.undo && vf.undo.length)) box.appendChild(this._varDone(t));
        return;
      }
      var items = vf.items;
      var counts = { all: items.length, empty: items.filter(function (i) { return i.status === 'empty'; }).length,
        differs: items.filter(function (i) { return i.status === 'differs'; }).length };
      var list = el('div', { class: 'vf' });
      var summary = el('span', { class: 'grow', role: 'status' });
      var applyBtn = el('button', { type: 'button', class: 'btn primary' });
      var selected = function () { return items.filter(function (i) { return i.checked && !i.state; }); };
      var refresh = function () {
        var n = selected().length;
        summary.textContent = n + ' of ' + items.filter(function (i) { return !i.state; }).length + ' selected';
        applyBtn.textContent = 'Apply ' + n;
        applyBtn.disabled = !n || !self._canEdit() || !!vf.applying;
      };
      var paintRows = function () {
        list.textContent = '';
        var shown = items.filter(function (i) { return vf.filter === 'all' || i.status === vf.filter; });
        if (!shown.length) list.appendChild(el('div', { class: 'sub', text: 'Nothing in this view.' }));
        shown.slice(0, vf.limit).forEach(function (it) { list.appendChild(self._varRow(t, it, refresh)); });
        if (shown.length > vf.limit) {
          list.appendChild(el('button', { type: 'button', class: 'btn', onclick: function () { vf.limit += 100; paintRows(); } },
            ['Show ' + Math.min(100, shown.length - vf.limit) + ' more (' + (shown.length - vf.limit) + ' left)']));
        }
      };
      var tab = function (key, label) {
        return el('button', { type: 'button', 'aria-pressed': String(vf.filter === key), onclick: function () { vf.filter = key; vf.limit = 60; self._paintVarfill(t); } },
          [label + ' ' + counts[key]]);
      };
      if (!items.length) {
        box.appendChild(el('div', { class: 'sub', text: 'No labels to propose.' }));
      } else {
        box.appendChild(el('div', { class: 'vf-bar' }, [el('div', { class: 'vf-tabs', role: 'group', 'aria-label': 'Show' },
          [tab('all', 'All'), tab('empty', 'Missing'), tab('differs', 'Differs')])]));
        var pick = function (fn) { return function () { items.forEach(function (i) { if (!i.state) i.checked = fn(i); }); self._paintVarfill(t); self._persist(); }; };
        box.appendChild(el('div', { class: 'vf-bar' }, [
          el('button', { type: 'button', class: 'btn', onclick: pick(function (i) { return i.status === 'empty'; }) }, ['Select missing']),
          el('button', { type: 'button', class: 'btn', onclick: pick(function () { return true; }) }, ['All']),
          el('button', { type: 'button', class: 'btn', onclick: pick(function () { return false; }) }, ['None']),
          summary, applyBtn]));
        box.appendChild(list);
        paintRows();
        refresh();
      }
      applyBtn.addEventListener('click', function () { self._applyVarfill(t, selected(), applyBtn); });
      if (vf.applied || (vf.undo && vf.undo.length)) box.appendChild(this._varDone(t));
    }

    _varRow(t, it, refresh) {
      var self = this;
      var field = it.field === 'label' ? '' : (it.field === 'question' ? 'question text' : it.field);
      var cb = el('input', { type: 'checkbox', 'aria-label': 'Apply ' + (field || 'label') + ' for ' + it.name });
      cb.checked = !!it.checked;
      cb.disabled = !!it.state;
      cb.addEventListener('change', function () { it.checked = cb.checked; refresh(); self._persist(); });
      var area = el('textarea', { rows: '1', 'aria-label': (field || 'Label') + ' for ' + it.name });
      area.value = it.edited !== undefined ? it.edited : it.value;
      area.disabled = !!it.state;
      area.addEventListener('input', function () {
        it.edited = area.value;
        if (!cb.checked && area.value.trim()) { cb.checked = true; it.checked = true; refresh(); }
        self._persist();
      });
      var row = el('div', { class: 'vf-row ' + (it.state === 'applied' ? 'done' : it.state === 'failed' ? 'failed' : it.status) }, [cb,
        el('div', { class: 'vf-head' }, [el('code', { text: it.name }), field ? el('span', { class: 'badge', text: field }) : null,
          el('span', { class: 'badge' + (it.status === 'differs' ? ' inconsistent' : ' empty'), text: it.status === 'differs' ? 'Differs' : 'Missing' }),
          el('span', { text: it.via === 'model' ? 'read by the model, check it' : 'codebook line' })]),
        area]);
      if (it.status === 'differs' && it.current) row.appendChild(el('div', { class: 'sub', text: 'Saved now: ' + it.current }));
      if (it.state === 'applied') row.appendChild(el('div', { class: 'sub ok', text: 'Applied.' }));
      if (it.state === 'failed') row.appendChild(el('div', { class: 'sub err', text: it.message || 'Not applied.' }));
      if (it.evidence) row.appendChild(el('details', { class: 'src' }, [el('summary', { text: 'From the document' }), el('div', { text: '“' + it.evidence + '”' })]));
      return row;
    }

    _varDone(t) {
      var self = this;
      var vf = t.varfill;
      var line = el('div', { class: 'vf-done', role: 'status' });
      if (vf.applied) line.appendChild(el('span', { text: vf.applied + ' written to the variables. Reload the page to see them in the editor.' }));
      if (vf.undo && vf.undo.length) {
        line.appendChild(el('button', { type: 'button', class: 'btn', onclick: function () { self._undoVarfill(t); } }, ['Undo']));
      }
      if (vf.applied) line.appendChild(el('button', { type: 'button', class: 'btn', onclick: function () { window.location.reload(); } }, ['Reload page']));
      return line;
    }

    // One request per few hundred variables; the proxy merges each change into the variable's own
    // record and skips any variable that changed since the suggestion was made.
    _writeVariables(vf, changes) {
      return fetch(this.api + 'index.php/api/assistant/labels', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ sid: this.recordId, file_id: vf.fileId, changes: changes })
      }).then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
          if (!r.ok || !r.body || r.body.status !== 'success') throw new Error((r.body && r.body.message) || 'The changes could not be saved.');
          return r.body;
        });
    }

    _applyVarfill(t, chosen, button) {
      var self = this;
      var vf = t.varfill;
      if (!chosen.length || vf.applying) return;
      vf.applying = true;
      button.disabled = true;
      var textOf = function (it) { return (it.edited !== undefined ? it.edited : it.value).trim(); };
      var byUid = {};
      chosen.forEach(function (it) {
        var c = byUid[it.uid] || (byUid[it.uid] = { uid: it.uid, expect: {} });
        c[it.field] = textOf(it);
        c.expect[it.field] = it.current || '';
      });
      var changes = Object.keys(byUid).map(function (k) { return byUid[k]; });
      var chunks = [];
      for (var i = 0; i < changes.length; i += 300) chunks.push(changes.slice(i, i + 300));
      var failed = 0;
      var next = function (n) {
        if (n >= chunks.length) {
          vf.applying = false;
          self._paintVarfill(t);
          self._persist();
          self._announce('Applied ' + vf.applied + ' changes' + (failed ? ', ' + failed + ' skipped' : '') + '.');
          return;
        }
        self._writeVariables(vf, chunks[n]).then(function (res) {
          var done = {};
          (res.applied || []).forEach(function (uid) { done[uid] = true; });
          var why = {};
          (res.skipped || []).forEach(function (s) { why[s.uid] = s.reason; });
          chunks[n].forEach(function (c) {
            chosen.filter(function (it) { return it.uid === c.uid; }).forEach(function (it) {
              if (done[c.uid]) {
                it.state = 'applied'; it.checked = false; vf.applied++;
                // What the undo needs: the values written and the values they replaced.
                var u = vf.undo.filter(function (x) { return x.uid === it.uid; })[0] || (vf.undo[vf.undo.length] = { uid: it.uid, before: {}, after: {} });
                u.before[it.field] = it.current || '';
                u.after[it.field] = textOf(it);
              } else { it.state = 'failed'; it.message = 'Not applied: ' + (why[c.uid] || 'the variable could not be updated') + '.'; failed++; }
            });
          });
          next(n + 1);
        }).catch(function (err) {
          vf.applying = false;
          chosen.forEach(function (it) { if (!it.state) { it.state = 'failed'; it.message = err.message; } });
          self._paintVarfill(t);
          self._persist();
        });
      };
      next(0);
    }

    _undoVarfill(t) {
      var self = this;
      var vf = t.varfill;
      var changes = (vf.undo || []).map(function (u) {
        var c = { uid: u.uid, expect: {} };
        Object.keys(u.after).forEach(function (f) { c[f] = u.before[f] || ''; c.expect[f] = u.after[f]; });
        return c;
      });
      if (!changes.length) return;
      var chunks = [];
      for (var i = 0; i < changes.length; i += 300) chunks.push(changes.slice(i, i + 300));
      var reverted = {};
      var next = function (n) {
        if (n >= chunks.length) {
          vf.items.forEach(function (it) { if (reverted[it.uid] && it.state === 'applied') { it.state = null; it.checked = true; vf.applied--; } });
          vf.undo = vf.undo.filter(function (u) { return !reverted[u.uid]; });
          self._paintVarfill(t);
          self._persist();
          return;
        }
        self._writeVariables(vf, chunks[n]).then(function (res) {
          (res.applied || []).forEach(function (uid) { reverted[uid] = true; });
          next(n + 1);
        }).catch(function (err) {
          t.parts.cards.appendChild(el('div', { class: 'note err', role: 'alert' }, [icon('warn'), el('span', { text: 'Undo failed: ' + err.message })]));
        });
      };
      next(0);
    }

    // ---------------------------------------------------------------- sending

    _updateComposer() {
      var text = this.input.value;
      var over = text.length > MAX_QUESTION;
      this.counter.textContent = text.length > MAX_QUESTION - 500 ? text.length + ' / ' + MAX_QUESTION : '';
      this.counter.className = over ? 'over' : '';
      var ready = !!(this.status && this.status.enabled && this.model);
      if (this.busy) {
        this.sendBtn.className = 'send stop';
        this.sendBtn.setAttribute('aria-label', 'Stop (Esc)');
        this.sendBtn.title = 'Stop (Esc)';
        this.sendBtn.replaceChildren(icon('stop'));
        this.sendBtn.disabled = false;
        this.sendBtn.type = 'button';
        this.sendBtn.onclick = this.stop.bind(this);
      } else {
        this.sendBtn.className = 'send';
        this.sendBtn.setAttribute('aria-label', 'Send');
        this.sendBtn.title = 'Send (Enter)';
        this.sendBtn.replaceChildren(icon('send'));
        this.sendBtn.type = 'submit';
        this.sendBtn.onclick = null;
        this.sendBtn.disabled = !ready || !text.trim() || over;
      }
      this.input.placeholder = this._hasSources() ? 'Ask about your sources or this project…'
        : this.recordId ? 'Ask about this project or the editor…' : 'Ask how to use the Metadata Editor…';
      this.clipBtn.hidden = !this.recordId;
      // The "Latest" button floats above the form, whatever height the sources list gives it.
      this.jump.style.bottom = (this.form.offsetHeight + 10) + 'px';
      var busy = !!this.busy;
      (this._attachButtons || []).forEach(function (b) { b.disabled = busy || !ready || (b._needsValue && !b._needsValue()); });
    }

    _autosize() {
      this.input.style.height = 'auto';
      this.input.style.height = Math.min(this.input.scrollHeight, 160) + 'px';
    }

    _submit() {
      if (this.busy) return;
      var text = this.input.value.trim();
      if (!text || text.length > MAX_QUESTION || !this.model) return;
      this.input.value = '';
      this._autosize();
      this.ask(text);
    }

    ask(question) {
      if (this.busy) return;
      if (!this.status) { this.open(false); this.input.value = question; this._updateComposer(); return; }
      var history = [];
      this.turns.forEach(function (t) {
        if (t.status === 'done' && t.text) {
          history.push({ role: 'user', content: t.question });
          history.push({ role: 'assistant', content: t.text });
        }
      });
      var turn = { question: question, text: '', status: 'streaming', steps: [], proposals: [], warnings: [], citations: [], reasoning: '', phase: 'Reading the context…' };
      if (!this.turns.length) this.log.textContent = '';
      this.turns.push(turn);
      this.log.appendChild(this._renderTurn(turn));
      this._scroll(true);
      this._run(turn, history.slice(-HISTORY_MESSAGES));
    }

    retry(t) {
      if (this.busy) return;
      var i = this.turns.indexOf(t);
      if (i < 0) return;
      var action = t.action || (t.question === 'Review this record' ? { type: 'review' } : null);
      if (action && action.type !== 'review' && !this._hasSources()) {
        this._attachError = 'Tick a source to retry.';
        return this._renderAttachBar();
      }
      this.turns.splice(i);
      this._persist();
      this._renderLog();
      if (!action) this.ask(t.question);
      else if (action.type === 'review') this.review();
      else if (action.type === 'docfill') this.fillFromDocument();
      else if (action.type === 'varfill') { this._varMore = !!(action.attrs && action.attrs.length > 1); this.labelVariables(); }
      else this._suggest(action.task, action.pointer);
    }

    stop() {
      if (this.busy && typeof this.busy.abort === 'function') this.busy.abort();
    }

    // ---------------------------------------------------------------- sources and one-shot drafts

    // Sources are the project's indexed files: its External resources, plus files added here. The
    // service reads and indexes each once (api/assistant/sources, api/assistant/attach); the panel never
    // holds a document's text. It sends the ids of the ticked sources with a turn, and the service sends
    // back only the passages that turn needs, labelled "name p.N".
    _ticksKey() { return STORE + 'sources:' + (this.recordId || 'app'); }

    _loadTicks() {
      try {
        var t = JSON.parse(sessionStorage.getItem(this._ticksKey()) || '{}');
        return t && typeof t === 'object' && !Array.isArray(t) ? t : {};
      } catch (e) { return {}; }
    }

    _saveTicks() {
      try { sessionStorage.setItem(this._ticksKey(), JSON.stringify(this._ticks)); } catch (e) { /* storage full: keep in memory */ }
    }

    // Ticked unless the curator unticked it here (or, for a source never touched here, the service says
    // it is not selected). A source that could not be read is never used.
    _isTicked(s) {
      if (s.state === 'failed') return false;
      var v = this._ticks[s.id];
      return v === undefined ? s.selected !== false : !!v;
    }

    _tickedSources() { var self = this; return this.sources.filter(function (s) { return self._isTicked(s); }); }
    _tickedIds() { return this._tickedSources().map(function (s) { return s.id; }); }
    _hasSources() { return this._tickedSources().length > 0; }

    _sourcesLabel() {
      var t = this._tickedSources();
      return t.length === 1 ? '“' + t[0].name + '”' : t.length + ' sources';
    }

    _stateText(s) {
      if (s.state === 'failed') return 'Could not read';
      return s.state === 'ready' ? 'Ready' : 'Indexing…';
    }

    _sourceMeta(s) {
      var bits = [];
      if (s.pages) bits.push(s.pages + (s.pages === 1 ? ' page' : ' pages'));
      bits.push(this._stateText(s));
      return bits.join(' · ');
    }

    _loadSources() {
      var self = this;
      if (!this.recordId) return;
      clearTimeout(this._srcTimer);
      this._srcTimer = null;
      var sid = this.recordId;
      var before = this._tickedIds().join('|');
      fetch(this.api + 'index.php/api/assistant/sources?sid=' + encodeURIComponent(sid), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
          if (sid !== self.recordId) return;
          if (!r.ok || !r.body || !Array.isArray(r.body.sources)) throw new Error((r.body && r.body.message) || 'Could not load the sources.');
          self._sourcesError = null;
          self.sources = r.body.sources.filter(function (s) { return s && s.id; });
        })
        .catch(function (err) { self._sourcesError = err.message; })
        .then(function () {
          self._renderAttachBar();
          self._updateComposer();
          // The empty-state suggestions depend on the ticked set; redraw them only when it changed.
          if (!self.turns.length && self._tickedIds().join('|') !== before) self._renderLog();
          self._pollSources();
        });
    }

    // Indexing finishes in the background: look again every few seconds until nothing is pending.
    _pollSources() {
      var self = this;
      clearTimeout(this._srcTimer);
      this._srcTimer = null;
      if (!this.recordId || this.panel.hidden || this._sourcesError) return;
      var pending = this.sources.some(function (s) { return s.state !== 'ready' && s.state !== 'failed'; });
      if (pending) this._srcTimer = setTimeout(function () { self._loadSources(); }, 3000);
    }

    // A file picked or dropped: the curator chooses where it goes (a chat-only source, or the project's
    // External resources) before it is uploaded. Someone who cannot edit has no such choice.
    _attach(file) {
      if (!this.recordId || this._attachLoading) return;
      this._attachNote = null;
      if (!/\.(pdf|txt|md)$/i.test(file.name)) {
        this._attachError = 'Add a PDF, text or Markdown file.';
        return this._renderAttachBar();
      }
      this._attachError = null;
      var self = this;
      var proceed = function () {
        if (!self._canEdit()) return self._upload(file, false);
        self._pendingFile = file;
        self._renderAttachBar();
        if (self._choiceFirst) self._choiceFirst.focus();
      };
      // The same bytes are already a source of this project: use it instead of asking where a second copy should live.
      this._fileHash(file).then(function (hash) {
        var dup = hash ? self.sources.filter(function (s) { return s.hash === hash; })[0] : null;
        if (!dup) return proceed();
        self._ticks[String(dup.id)] = true;
        self._saveTicks();
        self._attachNote = '“' + dup.name + '” is already a source of this project, so it is ticked for use.';
        self._announce(self._attachNote);
        self._renderAttachBar();
        self._updateComposer();
      }, proceed);
    }

    // SHA-256 of the file's bytes, the same value the service keys sources by; null where the browser has no crypto.subtle.
    _fileHash(file) {
      if (!(window.crypto && window.crypto.subtle && file.arrayBuffer)) return Promise.resolve(null);
      return file.arrayBuffer().then(function (buf) { return window.crypto.subtle.digest('SHA-256', buf); })
        .then(function (d) { return Array.prototype.map.call(new Uint8Array(d), function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); })
        .catch(function () { return null; });
    }

    _upload(file, save) {
      var self = this;
      this._pendingFile = null;
      this._attachError = null;
      this._attachNote = null;
      this._attachLoading = { name: file.name, save: !!save };
      this._renderAttachBar();
      var body = new FormData();
      body.append('file', file);
      body.append('sid', this.recordId);
      body.append('save', save ? '1' : '0');
      fetch(this.api + 'index.php/api/assistant/attach', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: body })
        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
          if (!r.ok || !r.body || !r.body.source) throw new Error((r.body && r.body.message) || 'The document could not be read.');
          var dup = r.body.duplicate_of;
          if (dup) {
            // The same bytes are already a source of this project: use that one, do not add a second.
            var id = dup && typeof dup === 'object' ? dup.id : dup;
            var known = self.sources.filter(function (s) { return String(s.id) === String(id); })[0];
            var name = (dup && typeof dup === 'object' && dup.name) || (known && known.name) || r.body.source.name || file.name;
            self._ticks[String(id)] = true;
            self._attachNote = 'Already in this project as “' + name + '”.';
          } else {
            self._ticks[r.body.source.id] = true;
            self._attachNote = (save ? 'Saved to External resources: “' : 'Added “') + (r.body.source.name || file.name) + '”.';
          }
          self._saveTicks();
          self._announce(self._attachNote);
        })
        .catch(function (err) { self._attachError = err.message; })
        .then(function () {
          self._attachLoading = null;
          self._renderAttachBar();
          self._updateComposer();
          self._loadSources();
        });
    }

    // Only a source added in the chat can be forgotten here; a resource is removed on the Resources page.
    _removeSource(s) {
      var self = this;
      this._attachError = null;
      this._attachNote = null;
      fetch(this.api + 'index.php/api/assistant/sources/' + encodeURIComponent(s.id) + '?sid=' + encodeURIComponent(this.recordId), {
        method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json' }
      })
        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error((r.body && r.body.message) || 'Could not remove “' + s.name + '”.');
          delete self._ticks[s.id];
          self._saveTicks();
          self._announce('Removed ' + s.name + '.');
        })
        .catch(function (err) { self._attachError = err.message; })
        .then(function () { self._loadSources(); });
    }

    _renderAttachBar() {
      var self = this;
      var bar = this.attachBar;
      if (!bar) return;
      bar.textContent = '';
      this._attachButtons = [];
      this._choiceFirst = null;
      var pending = this._pendingFile;
      if (!this.recordId || (!this.sources.length && !this._attachError && !this._attachLoading && !this._attachNote && !pending && !this._sourcesError)) {
        bar.hidden = true;
        return this._updateComposer();
      }
      bar.hidden = false;
      var action = function (label, cls, onclick, needsValue) {
        var b = el('button', { type: 'button', class: cls, onclick: onclick }, [label]);
        b._needsValue = needsValue || null;
        self._attachButtons.push(b);
        return b;
      };
      var dismiss = function (what) {
        return el('button', { type: 'button', class: 'icon-btn', title: 'Dismiss', 'aria-label': 'Dismiss',
          onclick: function () { self[what] = null; self._renderAttachBar(); } }, [icon('close')]);
      };

      if (this.sources.length) {
        var nUse = this._tickedSources().length;
        // Open while nothing is in use (the curator has to tick one) or when there are several to choose between.
        var open = typeof this._srcOpen === 'boolean' ? this._srcOpen : (nUse === 0 || this.sources.length > 1);
        bar.appendChild(el('div', { class: 'srchead' }, [
          el('button', { type: 'button', class: 'srctoggle', 'aria-expanded': open ? 'true' : 'false',
            title: 'Sources are the files the assistant searches to answer, fill and draft.',
            onclick: function () { self._srcOpen = !open; self._renderAttachBar(); } },
            [el('span', { class: 'chev', 'aria-hidden': 'true', text: open ? '▾' : '▸' }), 'Sources · ' + (nUse ? nUse + ' in use' : 'none in use')]),
          el('button', { type: 'button', class: 'link', onclick: function () { self.fileInput.click(); } }, ['+ Add file'])
        ]));
        var list = el('ul', { class: 'srclist', 'aria-label': 'Sources', hidden: open ? null : true });
        this.sources.forEach(function (s) {
          var failed = s.state === 'failed';
          var cb = el('input', { type: 'checkbox', 'aria-label': 'Search ' + s.name + ' when answering', title: failed ? (s.error || 'This file could not be read.') : 'Search this source when answering' });
          cb.checked = self._isTicked(s);
          cb.disabled = failed;
          cb.addEventListener('change', function () {
            self._ticks[s.id] = cb.checked;
            self._saveTicks();
            self._renderAttachBar();
            self._updateComposer();
            if (!self.turns.length) self._renderLog();
          });
          var chat = s.kind === 'attachment';
          list.appendChild(el('li', { class: 'src' + (failed ? ' bad' : '') }, [
            cb,
            el('div', { class: 'name' }, [
              el('b', { text: s.name, title: s.name }),
              el('span', { text: self._sourceMeta(s), title: failed ? (s.error || '') : (s.state === 'ready' ? '' : 'Searchable now; search quality improves when indexing finishes') })
            ]),
            el('span', { class: 'srcbadge' + (chat ? ' chat' : ''), text: chat ? 'Assistant only' : 'Project',
              title: chat ? 'Only you can search it with the assistant. The file itself is not kept, so it is not in Resources. Remove it with the × button.' : 'One of the project\u2019s External resources. Remove it on the Resources page.' }),
            chat ? iconButton('close', 'Remove ' + s.name + ' from the assistant', function () { self._removeSource(s); }) : null
          ]));
        });
        bar.appendChild(list);
      }
      if (this._sourcesError) {
        bar.appendChild(el('div', { class: 'doc err', role: 'alert' }, [icon('warn'),
          el('div', { class: 'name' }, [el('span', { text: this._sourcesError })]),
          el('button', { type: 'button', class: 'link', onclick: function () { self._sourcesError = null; self._loadSources(); } }, ['Retry'])]));
      }
      if (this._attachLoading) {
        bar.appendChild(el('div', { class: 'doc' }, [el('span', { class: 'spinner', 'aria-hidden': 'true' }),
          el('div', { class: 'name' }, [el('b', { text: this._attachLoading.name }),
            el('span', { text: this._attachLoading.save ? 'Saving and indexing…' : 'Reading and indexing…' })])]));
      }
      if (pending) {
        var keep = el('button', { type: 'button', class: 'opt primary', onclick: function () { self._upload(pending, false); } },
          [el('b', { text: 'Use with the assistant only' }), el('span', { text: 'Only you can search it with the assistant. The file is not kept.' })]);
        var saveBtn = el('button', { type: 'button', class: 'opt', onclick: function () { self._upload(pending, true); } },
          [el('b', { text: 'Add to project documents' }), el('span', { text: 'Also saved as a file in the project\u2019s External resources.' })]);
        this._choiceFirst = keep;
        bar.appendChild(el('div', { class: 'doc', role: 'group', 'aria-label': 'Add ' + pending.name + ' as a source' }, [icon('doc'),
          el('div', { class: 'name' }, [el('b', { text: pending.name, title: pending.name }), el('span', { text: 'Where should this file be kept?' })]),
          el('button', { type: 'button', class: 'link', onclick: function () { self._pendingFile = null; self._renderAttachBar(); } }, ['Cancel'])]));
        bar.appendChild(el('div', { class: 'choice' }, [keep, saveBtn]));
      }
      if (this._attachNote) {
        bar.appendChild(el('div', { class: 'doc', role: 'status' }, [icon('doc'),
          el('div', { class: 'name' }, [el('span', { text: this._attachNote })]), dismiss('_attachNote')]));
      }
      if (this._attachError) {
        bar.appendChild(el('div', { class: 'doc err', role: 'alert' }, [icon('warn'),
          el('div', { class: 'name' }, [el('span', { text: this._attachError })]), dismiss('_attachError')]));
      }
      if (!this.sources.length) return this._updateComposer();
      if (!this._hasSources()) {
        bar.appendChild(el('div', { class: 'doc-actions' }, [el('span', { text: 'Tick a source to search it when answering, filling or drafting.' })]));
        return this._updateComposer();
      }

      var actions = el('div', { class: 'doc-actions' });
      bar.appendChild(actions);
      if (this.fileId) {
        actions.appendChild(action('Label variables', 'btn primary', function () { self.labelVariables(); }, function () { return self._canEdit(); }));
        actions.appendChild(action('Draft data file description', 'btn', function () { self._suggest('datafile-description'); }));
        if (this._canEdit()) {
          var more = el('input', { type: 'checkbox', id: 'sa-vf-more', 'aria-label': 'Also propose descriptions and question text' });
          more.checked = !!this._varMore;
          more.addEventListener('change', function () { self._varMore = more.checked; });
          actions.appendChild(el('label', { for: 'sa-vf-more', style: 'display:inline-flex;gap:4px;align-items:center;cursor:pointer' },
            [more, 'Also descriptions and questions']));
        }
        return this._updateComposer();
      }
      var fields = this._fields();
      var pointers = Object.keys(fields).filter(function (p) { var f = self._field(p); return f.fillable !== false || f.rows; });
      if (!pointers.length) {
        actions.appendChild(el('span', { text: 'This project has no field the assistant can fill.' }));
        return this._updateComposer();
      }
      actions.appendChild(action('Fill fields from document', 'btn primary', function () { self.fillFromDocument(); }));
      if (!this._pickOpen) {
        actions.appendChild(el('button', { type: 'button', class: 'link', 'aria-expanded': 'false',
          onclick: function () { self._pickOpen = true; self._renderAttachBar(); if (self.attachSelect) self.attachSelect.focus(); } }, ['Draft one field…']));
        return this._updateComposer();
      }
      // Fields grouped by their form section, named as the form names them; required ones marked.
      var sel = el('select', { 'aria-label': 'Field to draft from the ticked sources' }, [el('option', { value: '', text: 'Choose a field to draft…' })]);
      var groups = {};
      var order = [];
      pointers.forEach(function (p) {
        var f = self._field(p);
        var key = f.section || 'Other fields';
        if (!groups[key]) { groups[key] = el('optgroup', { label: key }); order.push(key); }
        groups[key].appendChild(el('option', { value: p, title: p, text: f.label + (f.required ? ' *' : '') }));
      });
      order.forEach(function (k) { sel.appendChild(groups[k]); });
      this.attachSelect = sel;
      var draft = action('Draft', 'btn primary', function () { if (sel.value) self._suggest('resource-field', sel.value); }, function () { return !!sel.value; });
      sel.addEventListener('change', function () { self._updateComposer(); });
      bar.appendChild(el('div', { class: 'pick' }, [sel, draft,
        el('button', { type: 'button', class: 'link', onclick: function () { self._pickOpen = false; self._renderAttachBar(); } }, ['Cancel'])]));
      this._updateComposer();
    }

    _suggest(task, pointer) {
      if (this.busy || !this._hasSources() || !this.model) return;
      var self = this;
      var field = pointer ? this._field(pointer) : {};
      var label = pointer ? field.label : 'the data file description';
      var turn = { question: 'Draft “' + label + '” from ' + this._sourcesLabel(), action: { type: 'suggest', task: task, pointer: pointer || null },
        text: '', status: 'streaming', steps: [], proposals: [], warnings: [], citations: [], reasoning: '', phase: 'Reading the sources…' };
      this._startTurn(turn);
      var controller = new AbortController();
      this.busy = { abort: function () { controller.abort(); self.busy = null; turn.status = 'stopped'; self._finishTurn(turn); } };
      this._updateComposer();
      var payload = { sid: this.recordId, file_id: this.fileId || '', model: this.model, task: task, sources: this._tickedIds() };
      if (pointer) payload.field = pointer;
      fetch(this.api + 'index.php/api/assistant/suggest', {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
          self.busy = null;
          var suggestion = r.ok && r.body ? String(r.body.suggestion || '') : '';
          if (!suggestion) {
            turn.status = 'error';
            turn.error = (r.body && r.body.message) || 'The model did not return a value. Try again.';
            return self._finishTurn(turn);
          }
          var reason = String(r.body.reason || '');
          var current = r.body.current;
          turn.proposals = [{
            kind: pointer ? 'field' : 'datafile',
            pointer: pointer || ('datafile:' + (self.fileId || '') + ':description'),
            label: label,
            section: field.section || '',
            value: suggestion,
            reason: reason,
            status: current === undefined || current === null || current === '' ? 'empty' : 'improvement',
            current: current,
            source: 'document',
            file_id: pointer ? undefined : (self.fileId || undefined)
          }];
          turn.text = 'Drafted from the attached document. Edit it if needed, then fill it into the form.';
          turn.status = 'done';
          self._finishTurn(turn);
        })
        .catch(function (err) {
          if (controller.signal.aborted) return;
          self.busy = null;
          turn.status = 'error';
          turn.error = err.message || 'The draft failed.';
          self._finishTurn(turn);
        });
    }

    _startTurn(turn) {
      if (!this.turns.length) this.log.textContent = '';
      this.turns.push(turn);
      this.log.appendChild(this._renderTurn(turn));
      this._scroll(true);
    }

    // A whole-record review is a job, not a stream: it takes many tool rounds, may outlive the tab's
    // request timeout, and the editor already knows how to poll and cancel jobs.
    review() {
      if (this.busy || !this.recordId || !this.model) return;
      this._startTurn({ question: 'Review this record', action: { type: 'review' }, text: '', status: 'streaming', steps: [], proposals: [],
        warnings: [], citations: [], reasoning: '', phase: 'Starting the review…' });
      this._runReview(this.turns[this.turns.length - 1]);
    }

    // Every fillable field checked against the attached document: empty ones get a value the document
    // states, filled ones an improvement where the document says more. Same job path as a review.
    fillFromDocument() {
      if (this.busy || !this.recordId || !this.model || !this._hasSources()) return;
      this._startTurn({ question: 'Fill fields from ' + this._sourcesLabel(), kind: 'document', action: { type: 'docfill' },
        text: '', status: 'streaming', steps: [], proposals: [], warnings: [], citations: [], reasoning: '', phase: 'Starting…' });
      this._runReview(this.turns[this.turns.length - 1]);
    }

    // Every variable of the open data file read against the attached codebook, questionnaire or data
    // dictionary: codebook lines are read directly, the model reads what they do not state. The result
    // is a list the curator ticks and applies; nothing is written until then.
    labelVariables() {
      if (this.busy || !this.recordId || !this.fileId || !this.model || !this._hasSources() || !this._canEdit()) return;
      var attrs = this._varMore ? ['label', 'description', 'question'] : ['label'];
      this._startTurn({ question: 'Label variables from ' + this._sourcesLabel(), kind: 'variables', attrs: attrs,
        action: { type: 'varfill', attrs: attrs }, text: '', status: 'streaming', steps: [], proposals: [], warnings: [],
        citations: [], reasoning: '', phase: 'Starting…' });
      this._runReview(this.turns[this.turns.length - 1]);
    }

    _runReview(turn) {
      var self = this;
      var stopped = false;
      this.busy = { abort: function () { stopped = true; self.busy = null; turn.status = 'stopped'; self._finishTurn(turn); } };
      this._updateComposer();
      turn.phase = 'Starting…';
      this._paintAnswer(turn);
      var body = { model: this.model, sid: this.recordId || '', section: this.section || '', file_id: this.fileId || '',
        sources: this._tickedIds() };
      if (turn.kind === 'document' || turn.kind === 'variables') body.kind = turn.kind;
      if (turn.kind === 'variables') body.attrs = turn.attrs || ['label'];
      fetch(this.api + 'index.php/api/assistant/review', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body)
      }).then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
          if (!r.ok || !r.body || !r.body.job_id) throw new Error((r.body && r.body.message) || 'Could not start.');
          if (stopped) { self._cancelJob(r.body.job_id); return; }
          turn.jobId = r.body.job_id;
          self._persist(); // the id survives a reload; the panel resumes polling
          self._pollReview(turn);
        })
        .catch(function (err) {
          if (stopped) return;
          self.busy = null;
          turn.status = 'error';
          turn.error = err.message;
          self._finishTurn(turn);
        });
    }

    // Polls a job already started (this tab or before a reload). The job lives on the service, so a
    // reload loses nothing but the timer.
    _pollReview(turn) {
      var self = this;
      var jobId = turn.jobId;
      var stopped = false;
      this.busy = { abort: function () {
        stopped = true;
        self._cancelJob(jobId);
        self.busy = null;
        turn.jobId = null;
        turn.status = 'stopped';
        self._finishTurn(turn);
      } };
      this._updateComposer();
      var phase = function (text) {
        if (turn.phase === text) return;
        turn.phase = text;
        self._paintAnswer(turn);
      };
      var working = turn.kind === 'variables' ? 'Reading the codebook…' : turn.kind === 'document' ? 'Reading the document…' : 'Reviewing the record…';
      phase(working);
      (function poll() {
        if (stopped) return;
        fetch(self.api + 'index.php/api/assistant/review/' + encodeURIComponent(jobId), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
          .then(function (p) {
            if (stopped) return;
            if (!p.ok || !p.body) throw new Error((p.body && p.body.message) || 'The review failed.');
            var job = p.body;
            if (job.status === 'done') { turn.jobId = null; return self._applyReview(turn, job.data || {}); }
            if (job.status === 'error' || job.status === 'cancelled') throw new Error(job.error || 'The review did not finish.');
            var progress = job.info && job.info.progress;
            if (job.status === 'queued') phase('Waiting for a free model slot (other people are using the assistant)…');
            else if (progress && progress.total) {
              phase(turn.kind === 'variables' ? 'Read ' + progress.done + ' of ' + progress.total + ' variables against the codebook…'
                : 'Checked ' + progress.done + ' of ' + progress.total + ' fields against the document…');
            } else phase(working);
            setTimeout(poll, 1500);
          })
          .catch(function (err) {
            if (stopped) return;
            self.busy = null;
            turn.jobId = null;
            turn.status = 'error';
            turn.error = /interrupted_by_restart/.test(err.message) ? 'The assistant service restarted while this was running. Start it again.' : err.message;
            self._finishTurn(turn);
          });
      })();
    }

    _cancelJob(jobId) {
      if (!jobId) return;
      fetch(this.api + 'index.php/api/assistant/review/' + encodeURIComponent(jobId), { method: 'DELETE', credentials: 'same-origin' }).catch(function () {});
    }

    // After a reload, a turn still marked streaming with a job id is a review in flight: pick it up
    // again instead of leaving a stuck spinner.
    _resumeReviews() {
      if (this.busy) return;
      var turn = this.turns.filter(function (t) { return t.status === 'streaming' && t.jobId; })[0];
      if (turn) this._pollReview(turn);
    }

    _applyReview(turn, data) {
      var steps = [];
      (data.steps || []).forEach(function (s) {
        if (s.stage === 'call') steps.push({ kind: 'tool', name: s.name, args: s.args, state: 'running' });
        else {
          var hit = null;
          for (var i = steps.length - 1; i >= 0; i--) if (steps[i].name === s.name && steps[i].state === 'running') { hit = steps[i]; break; }
          if (hit) { hit.state = s.stage === 'failed' ? 'fail' : 'ok'; hit.message = s.stage === 'failed' ? String(s.message || 'failed').slice(0, 160) : null; }
        }
      });
      turn.steps = steps;
      turn.text = data.text || '';
      turn.proposals = (data.proposals || []).map(function (p) {
        return { kind: p.kind || 'field', pointer: p.pointer, label: p.label, section: p.section, value: p.value,
          reason: p.reason, status: p.status, current: p.current, file_id: p.file_id, evidence: p.evidence, source: p.source, cite: p.cite,
          columns: p.columns, rows: p.rows };
      });
      turn.citations = data.citations || [];
      if (data.variables) {
        var vf = data.variables;
        turn.varfill = {
          fileId: vf.file_id, fileName: vf.file_name, stats: vf.stats || {}, filter: 'all', limit: 60, undo: [], applied: 0,
          items: (vf.items || []).map(function (it) {
            return { uid: it.uid, name: it.name, field: it.field, value: it.value, current: it.current || '', status: it.status,
              // Codebook lines start ticked; what the model read starts unticked, to be checked first.
              via: it.via, evidence: it.evidence, checked: it.status === 'empty' && it.via === 'codebook' };
          })
        };
      }
      turn.done = { timings: data.timings || {}, usage: data.usage || {}, model: data.model || null, served_by: data.served_by || null };
      var info = this._modelInfo();
      if (info) turn.meta = { label: info.label || info.alias, local: !!info.local, model: data.model || null };
      turn.status = 'done';
      this.busy = null;
      this._finishTurn(turn);
    }

    _finishTurn(turn) {
      turn.steps.forEach(function (s) { if (s.state === 'running') s.state = turn.status === 'stopped' ? 'fail' : 'ok'; });
      this._paintSteps(turn);
      this._paintAnswer(turn);
      this._paintNotes(turn);
      this._paintFoot(turn);
      this._repaintCards(turn);
      this._persist();
      this._updateComposer();
      this._scroll(false);
      if (turn.status === 'done') this._announce('Answer: ' + (turn.text || '').slice(0, 400));
      else if (turn.status === 'error') this._announce(turn.error || 'The review failed.');
      else this._announce('Stopped.');
    }

    _run(turn, history) {
      var self = this;
      var controller = new AbortController();
      this.busy = controller;
      this._updateComposer();
      this._announce('The assistant is answering.');
      var body = {
        question: turn.question, messages: history, model: this.model,
        sid: this.recordId || '', section: this.section || '', file_id: this.fileId || ''
      };
      var ids = this._tickedIds();
      if (ids.length) body.sources = ids;
      var renderTimer = null;
      var repaint = function () {
        if (renderTimer) return;
        renderTimer = setTimeout(function () { renderTimer = null; self._paintAnswer(turn); self._scroll(false); }, 80);
      };
      // Paced reveal. Some hosted models return the whole answer as one chunk after a silent wait; typing
      // it out keeps the chat feel. The step grows with the backlog (a block lands in about a second, a
      // real token stream is shown as it arrives), and nothing waits on it: a stop or error flushes at once.
      var pending = '';
      var drainTimer = null;
      var onDrained = null;
      var drain = function () {
        if (!pending) {
          drainTimer = null;
          if (onDrained) { var f = onDrained; onDrained = null; f(); }
          return;
        }
        var n = Math.max(3, Math.ceil(pending.length / 60));
        turn.text += pending.slice(0, n);
        pending = pending.slice(n);
        repaint();
        drainTimer = setTimeout(drain, 16);
      };
      var flushNow = function () {
        clearTimeout(drainTimer);
        drainTimer = null;
        onDrained = null;
        turn.text += pending;
        pending = '';
      };

      var handle = function (name, data) {
        switch (name) {
          case 'queued':
            turn.phase = 'Waiting for a free model slot (other people are using the assistant)…';
            self._paintAnswer(turn);
            break;
          case 'meta':
            turn.meta = { label: data.label, local: data.local, model: data.model };
            turn.citations = data.citations || [];
            turn.phase = 'Thinking…';
            self._paintAnswer(turn);
            break;
          case 'reasoning':
            turn.reasoning += data.text || '';
            if (self._reasoningTimer) clearTimeout(self._reasoningTimer);
            self._reasoningTimer = setTimeout(function () {
              self._reasoningTimer = null;
              self._paintSteps(turn);
              self._scroll(false);
            }, 120);
            break;
          case 'token':
            pending += data.text || '';
            if (!drainTimer) drain();
            break;
          case 'round':
            flushNow();
            // A new model round: what the last one wrote was a step towards the answer, not the answer.
            if (turn.text.trim()) turn.steps.push({ kind: 'interim', text: turn.text.trim().slice(0, 240) });
            turn.text = '';
            turn.phase = data.reason === 'repair' ? 'Finishing the answer…' : 'Thinking…';
            self._paintSteps(turn);
            self._paintAnswer(turn);
            break;
          case 'tool':
            self._toolEvent(turn, data);
            break;
          case 'proposal':
            var p = { kind: data.kind || 'field', pointer: data.pointer, label: data.label, section: data.section, value: data.value,
              reason: data.reason, status: data.status, current: data.current, file_id: data.file_id };
            turn.proposals.push(p);
            turn.parts.cards.appendChild(self._card(turn, p));
            self._scroll(false);
            break;
          case 'warning':
            self._warning(turn, data);
            break;
          case 'done':
            turn.done = data;
            // The authoritative text replaces the typed one once the reveal has caught up.
            if (pending) turn.finalText = data.text;
            else turn.text = data.text || turn.text;
            turn.status = 'done';
            break;
          case 'error':
            flushNow();
            turn.status = 'error';
            turn.error = friendlyError(data.message || '');
            turn.errorDetail = data.message || '';
            break;
        }
      };

      fetch(this.api + 'index.php/api/assistant/chat', {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
        body: JSON.stringify(body)
      }).then(function (res) {
        var type = res.headers.get('Content-Type') || '';
        if (!res.ok || type.indexOf('text/event-stream') < 0) {
          return res.json().catch(function () { return {}; }).then(function (b) {
            var msg = (b && b.message) || ('The assistant is unavailable (HTTP ' + res.status + ').');
            if (res.status === 401) msg = 'Your session has ended. Sign in again, then retry.';
            if (res.status === 429) msg = b.message || 'The assistant is busy. Wait a moment, then retry.';
            throw new Error(msg);
          });
        }
        var reader = res.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';
        return (function pump() {
          return reader.read().then(function (chunk) {
            if (chunk.done) { buffer += '\n\n'; parseSse(buffer, handle); return; }
            buffer = parseSse(buffer + decoder.decode(chunk.value, { stream: true }), handle);
            return pump();
          });
        })();
      }).catch(function (err) {
        if (controller.signal.aborted) {
          turn.status = 'stopped';
        } else {
          turn.status = 'error';
          turn.error = err && err.message && !/fetch|network/i.test(err.message) ? err.message : 'Could not reach the assistant. Check your connection, then retry.';
        }
      }).then(function () {
        // A finished answer is allowed to finish typing; anything else is shown as it stands.
        if (drainTimer && turn.status === 'done') {
          return new Promise(function (resolve) { onDrained = resolve; });
        }
        flushNow();
      }).then(function () {
        if (turn.finalText != null) { turn.text = turn.finalText; delete turn.finalText; }
        if (turn.status === 'streaming') {
          turn.status = 'error';
          turn.error = 'The answer was cut off before it finished.';
        }
        clearTimeout(renderTimer);
        turn.steps.forEach(function (s) { if (s.state === 'running') s.state = turn.status === 'stopped' ? 'fail' : 'ok'; });
        self.busy = null;
        self._paintSteps(turn);
        self._paintAnswer(turn);
        self._paintNotes(turn);
        self._paintFoot(turn);
        if ((turn.proposals || []).length > 1) self._repaintCards(turn);
        self._persist();
        self._updateComposer();
        self._scroll(false);
        if (turn.status === 'done') self._announce('Answer: ' + turn.text.slice(0, 400));
        else if (turn.status === 'error') { self._announce(turn.error); self._loadStatus(); }
        else self._announce('Stopped.');
      });
    }

    _toolEvent(turn, d) {
      var steps = turn.steps;
      var find = function (name) {
        for (var i = steps.length - 1; i >= 0; i--) if (steps[i].name === name && steps[i].state === 'running') return steps[i];
        return null;
      };
      if (d.stage === 'call') steps.push({ kind: 'tool', name: d.name, args: d.args, state: 'running' });
      else if (d.stage === 'result') {
        var s = find(d.name);
        if (s) s.state = 'ok';
        // A proposal tool returns an error object as its "result" when it refuses a pointer.
        if (s && d.name === 'propose_field_value' && /"error"/.test(d.preview || '')) { s.state = 'fail'; s.message = 'not a field of this project'; }
      } else if (d.stage === 'failed') {
        var f = find(d.name);
        var why = String(d.message || 'failed').slice(0, 160);
        if (f) { f.state = 'fail'; f.message = why; }
        else steps.push({ kind: 'tool', name: d.name, args: {}, state: 'fail', message: why, raw: d.raw_args });
      } else if (d.stage === 'repeat') {
        // Answered from an earlier identical call: nothing new to show.
      } else if (d.stage === 'refused' && !turn.budgetNoted) {
        // "capped" only defers surplus calls to the next round; "refused" means the budget is spent.
        turn.budgetNoted = true;
        turn.warnings.push({ tone: 'muted', text: 'The assistant reached its limit of checks for one question; the answer uses what it already found.' });
        this._paintNotes(turn);
      }
      turn.phase = 'Checking…';
      this._paintSteps(turn);
      this._paintAnswer(turn);
      this._scroll(false);
    }

    _warning(turn, w) {
      var text = null;
      var action = null;
      if (w.kind === 'token_cap') { text = 'The answer reached its length limit.'; action = 'continue'; }
      else if (w.kind === 'incomplete') text = 'This answer may be incomplete. ' + (w.reason === 'empty' ? 'The model returned no text.' : 'Try asking more specifically.');
      else if (w.kind === 'window_fit') text = 'Earlier messages were left out so this question would fit. Start a new chat for unrelated questions.';
      else if (w.kind === 'context_capped') text = null;
      if (!text) return;
      turn.warnings.push({ tone: w.kind === 'window_fit' ? 'muted' : 'warn', text: text, action: action });
      this._paintNotes(turn);
    }

    // ---------------------------------------------------------------- misc

    _scroll(force) {
      var log = this.log;
      if (force || this._follow !== false) {
        var self = this;
        requestAnimationFrame(function () { self._autoScrollUntil = Date.now() + 120; log.scrollTop = log.scrollHeight; });
        this._follow = true;
        this.jump.hidden = true;
      }
    }

    _announce(text) {
      this.announcer.textContent = '';
      var a = this.announcer;
      setTimeout(function () { a.textContent = text; }, 50);
    }
  }

  if (!customElements.get('pslm-server-chat')) customElements.define('pslm-server-chat', ServerChat);
  window.pslmServerChatMarkdown = renderMarkdown; // exposed for the fixture's checks
})();
