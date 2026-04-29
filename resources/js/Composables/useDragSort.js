import { ref } from 'vue'

/**
 * HTML5 drag-and-drop reordering helper for arrays.
 *
 * Multiple independent lists in the same component are supported via the
 * `scope` parameter: only items sharing a scope can be reordered against
 * each other (e.g. `'groups'` vs `'options:0'`, `'options:1'`...).
 */
export function useDragSort() {
    const dragScope = ref(null)
    const dragIndex = ref(null)
    const overScope = ref(null)
    const overIndex = ref(null)

    function onDragStart(scope, index, event) {
        dragScope.value = scope
        dragIndex.value = index
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', `${scope}:${index}`)
    }

    function onDragOver(scope, index, event) {
        if (dragScope.value !== scope) { return }
        event.preventDefault()
        event.dataTransfer.dropEffect = 'move'
        overScope.value = scope
        overIndex.value = index
    }

    function onDragLeave(scope, index) {
        if (overScope.value === scope && overIndex.value === index) {
            overScope.value = null
            overIndex.value = null
        }
    }

    function onDrop(scope, targetIndex, list, event) {
        if (dragScope.value !== scope) { return }
        event.preventDefault()
        const fromIndex = dragIndex.value
        reset()
        if (fromIndex == null || fromIndex === targetIndex) { return }
        const [moved] = list.splice(fromIndex, 1)
        list.splice(targetIndex, 0, moved)
    }

    function onDragEnd() { reset() }

    function reset() {
        dragScope.value = null
        dragIndex.value = null
        overScope.value = null
        overIndex.value = null
    }

    function isDragging(scope, index) {
        return dragScope.value === scope && dragIndex.value === index
    }

    function isOver(scope, index) {
        return overScope.value === scope && overIndex.value === index
    }

    return { onDragStart, onDragOver, onDragLeave, onDrop, onDragEnd, isDragging, isOver }
}
