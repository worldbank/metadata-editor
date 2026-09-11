# Column diff (variables vs CSV) – UX and options review

## Current state

- **API**: `GET /api/datafiles/columns_diff/{sid}/{file_id}` returns:
  - `in_sync`: true only when DB variable names and CSV header names match exactly.
  - `columns_in_db_not_in_csv`: variable names in metadata that are **not** in the CSV (metadata has “extra” variables).
  - `columns_to_remove_from_csv`: column names in the CSV that are **not** in metadata (metadata is “missing” variables).
- **Frontend**: Header icon (orange `mdi-file-document-alert-outline`) when `!in_sync`, with tooltip summarizing both lists.

---

## 1. Best approach to show the issues

### Option A: Dialog opened from the header icon (recommended)

- **Trigger**: Click the existing warning icon in the variables header.
- **Content**:
  - Short explanation: “Variable metadata does not match the CSV columns for this file.”
  - Two clear sections:
    1. **In metadata but not in CSV** – list variable names. Meaning: these variables exist in the editor but there is no column with that name in the CSV (e.g. variable was removed from the file, or CSV was replaced).
    2. **In CSV but not in metadata** – list column names. Meaning: the CSV has these columns but there is no variable defined for them (e.g. new columns added to the file).
  - **Actions** (see below) as buttons per section, plus “Close” / “Review only”.
- **Pros**: Keeps the header uncluttered, focuses attention when the user cares, room for lists and actions. Reuses the same `columns_diff` payload; optional `?include_names=1` only if you want full db/csv name lists in the same response.

### Option B: Expandable bar below the header

- A collapsible strip that appears when `!in_sync`, with the same two lists and actions.
- **Pros**: Always visible without a click. **Cons**: Uses vertical space and can feel noisy; less room for long lists.

### Option C: Tooltip / popover only

- Keep current tooltip or replace with a popover that shows the two lists (no actions).
- **Pros**: Minimal UI. **Cons**: Long lists are awkward in a tooltip; no room for safe, explicit actions.

**Recommendation**: Use **Option A (dialog)**. Keep the header icon as the entry point; clicking it opens a “Column mismatch” dialog with the two lists and per-section actions. Optionally show a short summary in the tooltip (e.g. “2 in metadata only, 3 in CSV only – click for details”).

---

## 2. Options to offer (actions)

### 2a. Variables in metadata but NOT in CSV (“extra” in DB)

| Option | Description | Risk / notes |
|--------|-------------|--------------|
| **Delete these variables from metadata** | Permanently remove the listed variable definitions for this file. | **Destructive.** Only offer after explicit confirmation: “Delete N variable(s): … This cannot be undone.” Resolve names → UIDs via store or a small API (e.g. variables by file already in store). Use existing `POST /api/variables/delete/{sid}` with body `{ uid: [...] }`. **Caveat**: If a variable is used as a weight (`var_wgt_id`), backend already rejects delete; show backend error in the dialog. |
| **Keep / do nothing** | Just close the dialog; no change. | Safe. User may have a different CSV to upload later or may want to keep metadata for reporting. |

**Recommendation**: Offer **“Remove from metadata”** (or “Delete N variables”) with a confirmation step that lists the variable names and states that deletion cannot be undone. No need for “sync CSV to metadata” (i.e. remove columns from CSV) in the editor; that would be a data-file operation elsewhere.

---

### 2b. Columns in CSV but NOT in metadata (“missing” in DB)

| Option | Description | Risk / notes |
|--------|-------------|--------------|
| **Add variables from CSV** | Create one variable per listed column name (minimal metadata: `name` = column name, `labl` = column name or “Untitled”, `file_id`/`fid`, empty or default format/categories). | **Safe.** New rows only. Use existing create endpoint in a loop, or add a bulk-create endpoint (e.g. `POST /api/variables/create_from_columns/{sid}` with `{ file_id, column_names: [...] }`) for better performance and atomicity. After success, refresh variables and re-fetch `columns_diff` so the icon updates. |
| **Do nothing** | User will add variables manually or ignore. | Safe. |

**Recommendation**: Offer **“Add N variables from CSV”** (or “Create variables for these columns”). List the column names in the dialog; on confirm, create variables (one-by-one or via a dedicated bulk endpoint) then refresh. Optional: “Infer type from CSV” in a later phase (would require reading CSV sample and mapping to `var_format.type`).

---

## 3. Summary of recommended UX

1. **Discovery**: Keep the current header icon when `!columns_diff.in_sync`; tooltip can summarize counts.
2. **Details**: Clicking the icon opens a **“Column mismatch”** dialog with:
   - Brief explanation.
   - **In metadata only**: list + **“Remove N variables from metadata”** (with confirm step).
   - **In CSV only**: list + **“Add N variables from CSV”** (with confirm step).
   - **Close** / **Review only**.
3. **After actions**: On successful delete or create, refresh variables, call `fetchColumnsDiff()` again, and close the dialog (or update the dialog content). Header icon disappears when `in_sync` becomes true.

---

## 4. API / backend notes

- **columns_diff**: Current API is enough for the dialog. Use `include_names=1` only if you want to show full “DB names” / “CSV names” in the UI; the two diff arrays are sufficient for the lists and for “delete by name” / “create by name”.
- **Delete**: Existing `POST /api/variables/delete/{sid}` with `{ uid: [...] }` supports bulk delete. Frontend must map “variables in metadata but not in CSV” (names) to UIDs (e.g. from `variables` in store for this `fid`).
- **Create**: Either loop on existing `POST /api/variables/create/{sid}` (or variable_groups create) or add a single **bulk-create** endpoint that accepts `file_id` and `column_names[]`, creates minimal variables, and returns created UIDs. Bulk create improves performance and keeps “add from CSV” one transactional step.
- **Weight usage**: Backend already blocks deletion of a variable that is referenced as `var_wgt_id`; surface that error in the dialog (e.g. “Cannot delete … used as weight variable”).

---

## 5. Edge cases

- **No CSV**: API returns `csv_exists: false` and no diff arrays. You can either hide the mismatch icon or show a different state (e.g. “CSV not found”) without offering delete/add column actions.
- **Empty CSV or no headers**: Backend may return empty or error; handle empty lists and avoid offering “Add 0 variables.”
- **Very long lists**: In the dialog, cap display (e.g. “First 20 + N more”) or make the list scrollable with a max height.
- **Concurrent edit**: If another user or tab deletes/creates variables, a subsequent “Remove” or “Add” might partially fail; show API errors and refresh diff after so the icon reflects current state.

---

## 6. Copy / i18n suggestions

- Dialog title: “Variables vs CSV columns”
- Section labels: “In metadata only (not in CSV)”, “In CSV only (not in metadata)”
- Buttons: “Remove N from metadata”, “Add N from CSV”, “Close”
- Confirm delete: “Remove N variable(s) from metadata? This cannot be undone. Variables: …”
- Confirm add: “Create N variable(s) for these columns? Names: …”
- Weight error: “Cannot delete: [name] is used as a weight variable.”

This gives a clear, safe way to show column-diff issues and to offer “delete variables not in CSV” and “add variables from CSV” with minimal risk and reuse of existing APIs.
