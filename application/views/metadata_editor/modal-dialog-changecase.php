<template v-if="changeCaseDialog">
  <v-row justify="center">
    <v-dialog
      v-model="changeCaseDialog"
      scrollable
      max-width="300px"
    >
      
      <v-card>
        <v-card-title>Change case</v-card-title>
        <v-divider></v-divider>
        <v-card-text>


        <div class="form-group">
            <label for="exampleFormControlSelect1">Type</label>
            <select class="form-control" id="exampleFormControlSelect1">
            <option>Sentence case</option>
            <option>Title Case</option>
            <option>UPPERCASE</option>
            <option>LOWERCASE</option>      
            </select>
        </div>

        <div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1" >
                <label class="form-check-label" for="defaultCheck1">
                    Name
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="defaultCheck1" >
                <label class="form-check-label" for="defaultCheck1">
                    Label
                </label>
            </div>
        </div>

        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions>            
            <button type="button" @click="changeCaseDialog = false" class="btn btn-block btn-primary btn-sm mb-2">Apply</button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-row>
</template>