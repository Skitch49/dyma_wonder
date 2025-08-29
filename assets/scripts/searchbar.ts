import { createApp } from "vue";

createApp({
    compilerOptions: {
        delimiters: ["${", "}$"],
    },
    data() {
        return {
            timeout: null as any,
            isLoading: false,
            questions: null as any,
        };
    },
    methods: {
        updateInput() {
            clearTimeout(this.timeout);
            this.timeout = setTimeout(async () => {
                this.isLoading = true;
                const value = (this.$refs.input as HTMLInputElement).value;
                if (value?.length) {
                    try {
                        const response = await fetch(
                            `/question/search/${value}`
                        );
                        const body = await response.json();
                        this.questions = JSON.parse(body);
                        console.log(this.questions);
                    } catch (e) {
                        console.error(
                            "Erreur dans la récupération des questions",
                            e
                        );
                        this.questions = null;
                    } finally {
                        this.isLoading = false;
                    }
                } else {
                    this.questions = null;
                    this.isLoading = false;
                }
            }, 1000);
        },
    },
}).mount("#search");
