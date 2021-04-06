<?php

// Este esl codigo en el controller

public function inicio(Request $request){
    if ( !session()->has('search')) {
        session()->put('search', null);
    }
    if ( !session()->has('status')){
        session()->put('status', null);
    }
    if ( !session()->has('show')){
        session()->put('show', null);
    }
    if ( !session()->has('types')){
        session()->put('types', null);
    }
    if ( !session()->has('order')){
        session()->put('order', null);
    }

    return Inertia::render("application/Index", [
        'filters' => session()->only(['search', 'status', 'show', 'types', 'order']),
        'applications' => Application::whereNotIn('estado', [2])
            ->whereRaw('TIMESTAMPDIFF(MONTH, fecha_solicitud, now()) < 6')
            ->filter(request()->only('search', 'status', 'types', 'order'))
            ->paginate(request()->get('show', 10)),
    ]);
}


// Este es el codigo en el modelo

public function scopeFilter(Builder $query, array $filters){
    if ( ! request("page")) {
        session()->put('search', $filters['search'] ?? null);
        session()->put('status', $filters['status'] ?? null);
        session()->put('types', $filters['types'] ?? null);
        session()->put('order', $filters['order'] ?? null);
    }

    $query->when(array_key_exists("search", $filters) && $filters["search"], function ($query) use ($filters) {
        $query->whereHas('neighbor', function ($query) use ($filters){
            $query->where('nombre', 'LIKE', '%'.$filters['search'].'%')
                ->orWhere('rut', 'LIKE', '%'.$filters['search'].'%')
                ->orWhere('modo_entrega', 'LIKE', '%'.$filters['search'].'%');
        });
    })->when(session("status"), function ($query, $state) {
        $query->where('estado', $state);
    })->when(array_key_exists("types", $filters) && $filters["types"], function ($query) use ($filters) {
        $query->whereHas('neighbor', function ($query) use ($filters){
            $query->where('modo_entrega', 'LIKE', '%'.$filters['types'].'%');
        });
    })->when(session('order'), function ($query, $order){
        switch ($order){
            case 1:
                $query->whereHas('neighbor', function ($query){
                    $query->orderByDesc('nombre')->orderBy('id_solicitud', 'desc');
                });
                break;
            case 2:
                $query->whereHas('neighbor', function ($query){
                    $query->orderByDesc('rut')->orderBy('id_solicitud', 'desc');
                });
                break;
        }
    })->when(session('order') == null, function ($query){
        $query->orderBy('id_solicitud', 'desc');
    });
}

// Este el codigo en el componente

<script>
import debounce from "lodash/debounce";
import pickBy from "lodash/pickBy";
import mapValues from "lodash/mapValues";
import AppLayout from '@/Layouts/AppNewLayout';
import Pagination from "../../components/Pagination";
import Solicitud from "@/components/Solicitud";
import Input from "@/Jetstream/Input";
export default {
    components: {Input, Solicitud, AppLayout, Pagination},
    props: {
        applications: Object,
        filters: Object,
    },
    data() {
        return {
        form: {
            search: this.filters.search,
                status: this.filters.status,
                show: this.filters.show,
                types: this.filters.types,
                order: this.filters.order,
            }
    }
    },
    watch: {
        form : {
            handler: debounce(function() {
                let query = pickBy(this.form)
                console.log(query);
                this.$inertia.get(this.route('requests', query))
            }, 500),
            deep: true,
        }
    },
    methods: {
        reset() {
        this.form = mapValues(this.form, () => null);
        }
    }
}
</script>


// La ruta

Route::get('/requests', [ApplicationController::class, 'inicio'])->name('requests');
