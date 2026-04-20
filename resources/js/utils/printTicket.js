/**
 * Print a ticket in a dedicated 80mm-wide window.
 *
 * Why a dedicated window instead of window.print() on the main page:
 * browsers tend to ignore @page { size: 80mm auto } when the main document
 * has a full desktop layout. A minimal standalone window with only the
 * ticket content respects the page size consistently across Chrome,
 * Safari and Firefox, even when the user chooses "Save as PDF".
 *
 * Falls back to window.print() if the popup is blocked.
 *
 * @param {string} elementId - id of the DOM node containing the ticket HTML
 */
export function printTicket(elementId = 'print-ticket') {
    const source = document.getElementById(elementId)
    if (!source) {
        window.print()
        return
    }

    const html = source.outerHTML
    const win = window.open('', '_blank', 'width=420,height=640')

    if (!win) {
        // Popup blocked — degrade to the legacy whole-page print flow.
        window.print()
        return
    }

    win.document.open()
    win.document.write(buildDocument(html))
    win.document.close()
    win.focus()

    // Defer print so the new document finishes layout.
    // 250ms is a conservative window; browsers fire afterprint
    // to close as soon as the user confirms or cancels.
    const triggerPrint = () => {
        try { win.print() } catch (_) { /* ignored */ }
        const closeWindow = () => { try { win.close() } catch (_) {} }
        win.addEventListener('afterprint', closeWindow)
        // Safety net: some browsers never emit afterprint.
        setTimeout(closeWindow, 1500)
    }

    if (win.document.readyState === 'complete') {
        setTimeout(triggerPrint, 250)
    } else {
        win.addEventListener('load', () => setTimeout(triggerPrint, 100))
    }
}

function buildDocument(innerHtml) {
    return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Ticket</title>
<style>
@page { size: 80mm auto; margin: 0; }
html, body {
    margin: 0;
    padding: 0;
    width: 80mm;
    background: #fff;
    color: #000;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.4;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
* { box-sizing: border-box; }
#print-ticket {
    display: block !important;
    width: 76mm;
    padding: 2mm;
    background: #fff;
}
#print-ticket * {
    font-family: 'Courier New', Courier, monospace;
    color: #000;
    background: transparent;
    border: 0;
    box-shadow: none;
    text-shadow: none;
    margin: 0;
    padding: 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
}
.ticket-bold, .ticket-bold * { font-weight: 700; }
.ticket-lg, .ticket-lg * { font-size: 14px; }
.ticket-small, .ticket-small * { font-size: 11px; }
.ticket-center { text-align: center; }
.ticket-indent { padding-left: 12px; }
.ticket-item { margin-bottom: 4px; }
.ticket-row {
    display: flex;
    justify-content: space-between;
    gap: 4px;
}
.ticket-row > span { max-width: 70%; }
.ticket-row > span:last-child {
    max-width: 30%;
    text-align: right;
    flex-shrink: 0;
    white-space: nowrap;
}
</style>
</head>
<body>${innerHtml}</body>
</html>`
}
