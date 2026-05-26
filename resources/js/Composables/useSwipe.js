export function useSwipe({ onLeft, onRight, threshold = 50 } = {}) {
    let startX = 0;

    function onTouchStart(e) {
        startX = e.touches[0].clientX;
    }

    function onTouchEnd(e) {
        const delta = e.changedTouches[0].clientX - startX;
        if (delta < -threshold) onLeft?.();
        else if (delta > threshold) onRight?.();
    }

    return { onTouchStart, onTouchEnd };
}
