<script type="text/x-template" id="mx-products-template">
    <div class="mx-products">
        <div v-if="!compact && panels.length > 1" class="mx-tabs" role="tablist" aria-label="Product categories">
            <button v-for="(panel, index) in panels" :key="panel.key" type="button" role="tab" :aria-selected="selected === index" :tabindex="selected === index ? 0 : -1" @click="selected = index" @keydown.right.prevent="move(1, $event)" @keydown.left.prevent="move(-1, $event)" @keydown.home.prevent="moveTo(0, $event)" @keydown.end.prevent="moveTo(panels.length - 1, $event)">@{{ panel.label }}</button>
        </div>
        <div class="mx-product-grid" role="region" :aria-label="panels[selected]?.label + ' products'" aria-live="polite">
            <div v-for="product in panels[selected]?.products || []" :key="product.id" class="mx-product-cell">
                <x-shop::products.card />
                <div class="mx-product-extra"><p v-if="product.mx_brand" class="mx-kicker">@{{ product.mx_brand }}</p><p v-if="product.mx_benefit" class="mx-benefit-copy">@{{ product.mx_benefit }}</p></div>
            </div>
        </div>
    </div>
</script>
<script type="module">
    app.component('v-mx-products', {
        template: '#mx-products-template',
        props: { panels: { type: Array, required: true }, compact: { type: Boolean, default: false } },
        data() { return { selected: 0 }; },
        methods: {
            move(step, event) { this.moveTo((this.selected + step + this.panels.length) % this.panels.length, event); },
            moveTo(index, event) {
                this.selected = index;
                event.currentTarget.parentElement.querySelectorAll('[role="tab"]')[index].focus();
            }
        }
    });
</script>
