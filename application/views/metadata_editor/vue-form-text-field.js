//Metadata Form ///////////////////////////////////////////////////
Vue.component('form-text-field', {
    props: ['title', 'css_class','path', 'field'],
    data() {
        return {
            x:''
        }
    },
    mounted:function(){
    },
    methods: {
    },
    created() {
        ///alert(1);
      },
      beforeUpdate () {
          //this.x=this.field;
          alert(this.field);
      },
    computed: {
        formData () {
            return this.$deepModel('formData')
        },
        activeSection()
        {
            return this.$store.state.treeActiveNode;
        }
    },
    watch: {
    },
    template: `
        <div :class="'form-text-field'"  style="background:red;color:white;"  >
        {{field}}
            ADFADSFADSFASDFASDFASDFSADFASDFADSFASDFASDFASDFADS {{field}}
            xxxxxx
            {{title}}

        </div>
    `
})


