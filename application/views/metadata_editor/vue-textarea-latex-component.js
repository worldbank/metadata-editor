Vue.component('v-textarea-latex', {
    props: ['value'],
    data() {
        return {
            inputValue: this.value,
            is_preview: false,
            preview_toggle: 0
        }
    },
    mounted:function(){
        this.loadMathJax();
    },
    watch: {
        inputValue(newVal) {
            this.$emit('input', newVal);
        },
        value(newVal) {
            this.inputValue = newVal;
        }
    },
    methods: {    
        togglePreview: function(value){
            if (this.is_preview==false){
                this.renderLatex();
            }

            this.is_preview = value
        },   
        isScriptLoaded: function(src) {
          return document.querySelectorAll(`script[src="${src}"]`).length > 0;
        },
        loadMathJax:function(){
          
          //check if script is already loaded
          if (this.isScriptLoaded('https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js')){
            return;
          }

            let MathJax = {
                tex: {inlineMath: [['$', '$'], ['\\(', '\\)']]},
                startup: {
                  ready: function () {
                    MathJax.startup.defaultReady();
                    document.getElementById('render').disabled = false;
                  }
                }
              }

            let script = document.createElement('script');
            script.type = 'text/javascript';
            script.async = true;
            script.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';            
            document.head.appendChild(script);
        },
        renderLatex: function() {            

            //check if textarea is empty
            if (this.$refs.myTextarea.value == '') {
                return;
            }

            const input = this.$refs.myTextarea.value; // Accessing textarea value using ref
            const output = this.$refs.output; // Assuming you also have a ref="output" for the output element
            output.innerHTML = input;
    
            MathJax.texReset();
            MathJax.typesetClear();
            MathJax.typesetPromise()
              .catch(function (err) {
                output.innerHTML = '';
                output.appendChild(document.createElement('pre')).appendChild(document.createTextNode(err.message));
              })
              .then(function() {
                // Assuming 'button' is also managed via refs or another approach
                // button.disabled = false;
              });
        }
    },
    computed: {        
    },
    template: `
        <div class="v-textarea-latex" >
            <div class="">

                <v-btn-toggle
                    v-model="preview_toggle"
                    mandatory
                >
                    <v-btn small text @click="togglePreview(false)">Text</v-btn>
                    <v-btn small text @click="togglePreview(true)">Preview</v-btn>
                </v-btn-toggle>

                <span class="text-muted">LaTeX equations are supported. </span>
                
            </div>
            <v-textarea 
                v-show="is_preview==false"
                ref="myTextarea"
                v-model="inputValue"                
                auto-grow
                full-width
                class="v-textarea-field border-top"
                clearable
                row-height="40"
                max-height="200"
                max-rows="5"                            
                density="compact"
                rows="5"
                placeholder="Enter LaTeX code here"                
                >
            </v-textarea>
      
            <div ref="output" v-show="is_preview==true" class="border elevation-1" style="padding:15px;width:100%;max-height:400px;overflow:auto;white-space: pre-wrap;"></div>
        </div>
    `
});

