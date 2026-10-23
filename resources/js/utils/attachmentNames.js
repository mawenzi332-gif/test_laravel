export function duplicateDisplayNames(items, getName = (item) => item.name) {
    const totals = new Map();
    items.forEach((item) => {
        const name = getName(item);
        totals.set(name, (totals.get(name) ?? 0) + 1);
    });

    const seen = new Map();
    return items.map((item) => {
        const name = getName(item);
        const occurrence = seen.get(name) ?? 0;
        seen.set(name, occurrence + 1);
        return totals.get(name) > 1 && occurrence > 0 ? addSuffix(name, occurrence) : name;
    });
}

function addSuffix(name, index) {
    const extensionStart = name.lastIndexOf('.');
    if (extensionStart <= 0) return `${name} (${index})`;
    return `${name.slice(0, extensionStart)} (${index})${name.slice(extensionStart)}`;
}
