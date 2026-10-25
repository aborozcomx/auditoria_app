<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from '@lucide/vue';
import type { PaginatedData } from '@/types';
import { Button } from '@/components/ui/button';

interface Props {
    pagination: PaginatedData<any>;
    perPage?: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'change-per-page', value: number): void;
}>();

const onPerPageChange = (event: Event) => {
    const val = Number((event.target as HTMLSelectElement).value);
    emit('change-per-page', val);
};
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-4 px-2 py-4 sm:flex-row"
    >
        <!-- Count info & per page selector -->
        <div
            class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
        >
            <span v-if="pagination.total > 0">
                Mostrando
                <span class="font-medium text-foreground">{{
                    pagination.from
                }}</span>
                a
                <span class="font-medium text-foreground">{{
                    pagination.to
                }}</span>
                de
                <span class="font-medium text-foreground">{{
                    pagination.total
                }}</span>
                registros
            </span>
            <span v-else>No se encontraron registros</span>

            <div class="flex items-center gap-2">
                <span class="text-xs">Filas:</span>
                <select
                    :value="perPage || pagination.per_page"
                    @change="onPerPageChange"
                    class="h-8 rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden"
                >
                    <option :value="5">5</option>
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Pagination Links -->
        <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
            <!-- First page -->
            <Button
                variant="outline"
                size="icon"
                class="hidden h-8 w-8 sm:flex"
                :disabled="pagination.current_page === 1"
                as-child
            >
                <Link
                    v-if="pagination.current_page !== 1"
                    :href="pagination.first_page_url"
                    preserve-state
                    preserve-scroll
                >
                    <ChevronsLeft class="h-4 w-4" />
                </Link>
                <span v-else>
                    <ChevronsLeft class="h-4 w-4" />
                </span>
            </Button>

            <!-- Previous page -->
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="!pagination.prev_page_url"
                as-child
            >
                <Link
                    v-if="pagination.prev_page_url"
                    :href="pagination.prev_page_url"
                    preserve-state
                    preserve-scroll
                >
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <span v-else>
                    <ChevronLeft class="h-4 w-4" />
                </span>
            </Button>

            <!-- Page numeric pills -->
            <template v-for="(link, i) in pagination.links" :key="i">
                <template
                    v-if="
                        !link.label.includes('Previous') &&
                        !link.label.includes('Next') &&
                        !link.label.includes('&laquo;') &&
                        !link.label.includes('&raquo;')
                    "
                >
                    <Button
                        v-if="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="icon"
                        class="h-8 w-8 text-xs"
                        as-child
                    >
                        <Link
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                            v-html="link.label"
                        />
                    </Button>
                    <span
                        v-else
                        class="px-2 text-xs text-muted-foreground"
                        v-html="link.label"
                    ></span>
                </template>
            </template>

            <!-- Next page -->
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="!pagination.next_page_url"
                as-child
            >
                <Link
                    v-if="pagination.next_page_url"
                    :href="pagination.next_page_url"
                    preserve-state
                    preserve-scroll
                >
                    <ChevronRight class="h-4 w-4" />
                </Link>
                <span v-else>
                    <ChevronRight class="h-4 w-4" />
                </span>
            </Button>

            <!-- Last page -->
            <Button
                variant="outline"
                size="icon"
                class="hidden h-8 w-8 sm:flex"
                :disabled="pagination.current_page === pagination.last_page"
                as-child
            >
                <Link
                    v-if="pagination.current_page !== pagination.last_page"
                    :href="pagination.last_page_url"
                    preserve-state
                    preserve-scroll
                >
                    <ChevronsRight class="h-4 w-4" />
                </Link>
                <span v-else>
                    <ChevronsRight class="h-4 w-4" />
                </span>
            </Button>
        </div>
    </div>
</template>
