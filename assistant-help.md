# Metadata Editor — help for this assistant

The Metadata Editor is the workbench curators use to describe a statistical study, an indicator, a
document or another resource before publishing it to a NADA catalogue. Everything here is metadata
about a study (its description, its data files, its variables, its documentation), not the
observations themselves.

## This page: the Projects list

`/index.php/editor` lists the projects your account can open. It is a starting point, not a record:
there is no single project on screen, so the assistant answers from the editor's help and
documentation only. To ask about a project, open it: each project opens at
`/index.php/editor/edit/<id>`, and the assistant there reads that project's saved metadata.

## Inside a project

A project is organised by section, reachable from the address after the `#`:

| Section | Address | What lives there |
|---|---|---|
| Study description | `#/study/<id>` | title, abstract, universe, analysis unit, geographic and temporal coverage, producer, citation |
| Data files | `#/datafiles` | the files attached to the study |
| Data file | `#/datafile/<id>` | one file's description |
| Variables | `#/variables/<id>` | variable names, labels, types, categories, derived flags |
| Variables validation | `#/variables-validation/<id>` | consistency problems found in a file's variables |
| Data explorer | `#/data-explorer/<id>` | actual data values; outside what this assistant reads |
| External resources | `#/external-resources` | questionnaires, reports and other documents of the project |
| Project package | `#/project-package` | the exportable bundle |
| Publish | `#/publish` | the publication queue and its checks |
| Change log | `#/change-log` | who changed what, and when |

## The workflow

1. **Draft** the description. Fields are validated against the project's template as you leave them.
2. **Attach and describe data files**, then their variables and categories.
3. **Check** the validation and variable-validation screens for inconsistencies.
4. **Publish** from `#/publish`, which runs the publication checks and hands the study to the
   catalogue. Publishing is a deliberate act by a curator; nothing happens by itself.

## What this assistant can do here

- **Answer questions** about the editor, and on a project page about that project's saved metadata.
  It sees what your account may read on that project, nothing more.
- **Search the project's sources.** Sources are the project's External resources and documents you
  add to the chat (PDF, text or Markdown). Tick the ones it should use; answers cite them as
  `name p.N`. A document added to the chat can also be saved as an External resource.
- **Draft values as cards.** When you ask for a suggestion, an improvement or a review, each proposed
  value appears as a card with **Fill in form**, **Copy** and **Dismiss**.
- **Fill fields from the sources.** Every field the template allows is checked against the ticked
  sources; a value is proposed only when a source states it.
- **Review this record.** Finds required and recommended fields that are empty, unclear or
  inconsistent, and proposes values where the project's own content supports them.
- **On a data file page:** draft the file's description from its variables, and **Label variables**
  from a codebook or questionnaire among the sources. Accepted labels can be undone.

Drafting and filling need edit permission on the project; with view permission the assistant only
answers.

**Fill in form** types the value into the field as if you had typed it. It is not saved: you review
it and save it yourself. If the field lives in another section, the panel opens that section and fills
it there.

An administrator can mark a project **Keep on this server**: then only a model running on this
organisation's servers answers about it, and nothing from the project is sent to an external
provider.

## What it cannot do

- It cannot save, publish, delete, assign or share anything, and it will never tell you it has done
  such a thing. The only change it makes is what you accept: a value you fill into the form (unsaved)
  or variable labels you apply.
- It cannot read your screen, your selection, or any other tab or window. It knows which section is
  open and reads the project from the server.
- It cannot read data values, private fields, or projects you cannot open.
- It can be wrong, and it is not an authority on cataloguing practice. Treat every answer as a draft
  to check against the record, the template, and your own catalogue's rules.

## Questions it handles well

- "What does this record say about the universe and the analysis unit?"
- "Which required fields are empty?"
- "Is the geographic coverage more specific than 'National'?"
- "Check the indicator name against its measurement unit and definitions; list any inconsistencies."
- "What do the sources cover?"
- "Draft a shorter abstract that keeps the coverage dates."

## Questions it handles badly

- Anything needing data values, other projects, or the publication queue's live state.
- Anything requiring judgement about whether a study *should* be published.
- Word-for-word quoting of long fields: it paraphrases, and may change a date or a number. Check
  values against the source before saving.
