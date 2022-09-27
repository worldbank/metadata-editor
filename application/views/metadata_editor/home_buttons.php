<div class="row mt-3 mb-3">
    <div class="col"><h3>Projects</h3></div>
    <div class="col-3">
        <div class="btn-group float-right">
            <button type="button" class="btn btn-default">Create new project</button>
            <button type="button" class="btn btn-default dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-expanded="false">
                <span class="sr-only">Toggle Dropdown</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" @click="createProject('survey')">Microdata</a>
                <a class="dropdown-item" href="#" @click="createProject('document')">Document</a>
                <a class="dropdown-item" href="#" @click="createProject('table')">Table</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">Import from URL</a>
            </div>
        </div>
    </div>
</div>

