Vue.component('hello', {
    props: ['title', 'items', 'depth', 'css_class','path', 'field'],
    data() {
        return {
            showChildren: true
        }
    },
    mounted:function(){
        //collapse all sections by default
        if (this.depth>0){
            this.toggleChildren();
        }
    },
    methods: {
        toggleChildren() {
            this.showChildren = !this.showChildren;
        },
        toggleNode(event){
            alert("event toggleNode");
        },
        showFieldError(field,error){
            //field_parts=field.split("-");
            //field_name=field_parts[field_parts.length-1];
            //return error.replace(field,field_name);
            return error.replace(field,'');
        }
    },
    computed: {
        toggleClasses() {
            return {
                'fa-angle-down': !this.showChildren,
                'fa-angle-up': this.showChildren
            }
        },
        hasChildrenClass() {
            return {
                'has-children': this.nodes
            }
        },
        formData () {
            return this.$deepModel('formData')
        }
    },
    template: `
        <div>
            <h1>Hello world!</h1>
        </div>
    `
})