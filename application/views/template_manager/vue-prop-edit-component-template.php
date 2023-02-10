<template>
    <v-tabs background-color="transparent" class="mb-5">
        <v-tab  v-if="isField(prop.type)">Display</v-tab>
        <v-tab><span v-if="prop.enum && prop.enum.length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>Controlled vocabulary</v-tab>
        <v-tab>Default<span v-if="prop.default"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
        <v-tab v-if="isField(prop.type)"><span v-if="prop.rules && Object.keys(prop.rules).length>0"><v-icon style="color:green;">mdi-circle-medium</v-icon></span>Validation rules</v-tab>
        <v-tab>JSON</v-tab>

        <v-tab-item class="p-3"  v-if="isField(prop.type)">

            <!--display-->
            <div class="form-group">
                <label >Data type:</label>
                <select 
                    v-model="prop.type" 
                    class="form-control form-field-dropdown" >        
                    <option v-for="field_type in field_data_types">
                        {{field_type}}
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Display:</label>
                <select 
                    v-model="prop.display_type" 
                    class="form-control form-field-dropdown" >        
                    <option v-for="display_type in field_display_types">
                        {{display_type}}
                    </option>
                </select>

            </div>
            <!--end display -->

        </v-tab-item>

        <v-tab-item class="p-3">
            <!-- controlled vocab -->
            <template>
            <div class="form-group" >
                <label for="controlled_vocab">Controlled vocabulary:</label>
                <div class="border bg-white" style="max-height:300px;overflow:auto;">

                    <template v-if="isField(prop.type) || prop.type=='simple_array'">                        
                        <table-component @update:value="EnumListUpdate" :key="prop.key"  v-model="prop.enum" :columns="SimpleControlledVocabColumns" class="border m-2 pb-2" />
                    </template>
                    <template v-else>
                        <table-component @update:value="EnumListUpdate"  :key="prop.key"  v-model="prop.enum" :columns="prop.props" class="border m-2 pb-2" />
                    </template>
                </div>

            </div>
            </template>
            <!-- end controlled vocab -->
        </v-tab-item>
        <v-tab-item class="p-3">
            <!-- default -->
            <template v-if="prop.type!=='section_container' && prop.type!=='section' && prop.display_type">
                <div class="form-group" >
                    <label for="controlled_vocab">Default:</label>
                    <div class="border bg-white" style="max-height:300px;overflow:auto;" v-if="prop.type=='array'">
                        <table-component @update:value="DefaultUpdate" v-model="prop.default" :columns="prop.props" class="border m-2 pb-2" />
                    </div>
                    <div class="border bg-white" v-else>
                        <div v-if="prop.display_type=='textarea'">
                            <textarea class="form-control" style="height:200px;" v-model="prop.default"></textarea>
                        </div>
                        <div v-else-if="isField(prop.type)">
                            <input class="form-control" type="text" v-model="prop.default"/>
                        </div>                        
                    </div>
                </div>
            </template>
            <!-- end default -->
        </v-tab-item>
        <v-tab-item class="p-3" v-if="isField(prop.type)">
            <div class="form-group" >
                <label for="controlled_vocab">Validation rules:</label>
                <div class="bg-white border">
                    <validation-rules-component v-model="prop.rules" @update:value="RulesUpdate"  class="m-2 pb-2" />
                </div>
            </div>
        </v-tab-item>

        <v-tab-item class="p-3">
            <div class="form-group" >
                <label for="controlled_vocab">JSON:</label>
                <pre>{{prop}}</pre>
            </div>
        </v-tab-item>
    </v-tabs>

</template>