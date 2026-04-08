// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * TODO describe module class_schedule
 *
 * @module     block_customdashboard/class_schedule
 * @copyright  2026 Brain Station 23 <sales@brainstation-23.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { call as saveData } from "core/ajax";
import { call as getData } from "core/ajax";
export const init = (isTeacher, courseids) => {
    let scheduleData = [];
    let scheduleStudentData = [];
    let rowHeaders = [];
    let colHeaders = [];
    let mergedCells = new Map();
    let cellComments = new Map();
    let cellStyles = new Map();
    let undoStack = [];
    let redoStack = [];
    let selectedRow = -1,
        selectedCol = -1;
    let weekMode = 5;
    let darkMode = false;
    let searchTerm = "";
    let currentEditingCell = null;

    const defaultDays5 = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    const defaultDays7 = [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday",
    ];
    const defaultTimes = generateTimes(8, 17);

    function generateTimes(startHour, endHour) {
        let times = [];
        for (let h = startHour; h <= endHour; h++) {
            let period = h >= 12 ? "PM" : "AM";
            let hour12 = h > 12 ? h - 12 : h === 0 ? 12 : h;
            if (h === 12) period = "PM";
            times.push(`${hour12}:00 ${period}`);
        }
        return times;
    }

    function initDefault() {
        colHeaders = weekMode === 5 ? [...defaultDays5] : [...defaultDays7];
        rowHeaders = [...defaultTimes];
        scheduleData = Array(rowHeaders.length)
            .fill()
            .map(() =>
                Array(colHeaders.length)
                    .fill()
                    .map(() => ({ text: "", style: {} })),
            );
        mergedCells.clear();
        cellStyles.clear();
        cellComments.clear();
        // Sample data
        if (rowHeaders.length > 2 && colHeaders.length > 2) {
        }
        applyAllStylesFromMap();
        renderGrid();
        pushUndoState();
        if (!isTeacher) {
            loadFromLocal();
        } else {
            setTimeout(() => {
                showDefaultModal();
            }, 500);
        }

        updateStatus("Default schedule loaded");
    }

    function applyAllStylesFromMap() {
        for (let i = 0; i < scheduleData.length; i++)
            for (let j = 0; j < scheduleData[0].length; j++) {
                let key = `${i},${j}`;
                if (cellStyles.has(key))
                    scheduleData[i][j].style = { ...cellStyles.get(key) };
                else scheduleData[i][j].style = {};
            }
    }

    // Time Picker Popup for editing time slots (click on time column)
    function showTimePicker(rowIndex) {
        const modal = document.createElement("div");
        modal.className = "time-picker-modal";
        modal.innerHTML = `
        <div class="time-picker-content">
            <h4>✏️ Edit Time Slot</h4>
            <input type="time" id="timePickerInput" value="${convertToTimeInput(rowHeaders[rowIndex])}" step="60">
            <div class="time-picker-buttons">
            <button id="timePickerCancel" class="border">Cancel</button>
            <button id="timePickerConfirm" class="primary-btn">Update</button>
            </div>
        </div>
        `;
        document.body.appendChild(modal);

        const timeInput = modal.querySelector("#timePickerInput");
        const confirmBtn = modal.querySelector("#timePickerConfirm");
        const cancelBtn = modal.querySelector("#timePickerCancel");

        confirmBtn.onclick = () => {
            const newTimeValue = timeInput.value;
            if (newTimeValue) {
                const formattedTime = formatTimeFromInput(newTimeValue);
                rowHeaders[rowIndex] = formattedTime;
                renderGrid();
                updateDisplayPanel();
                pushUndoState();
                autoSaveToLocal();
                updateStatus(`Time slot updated to ${formattedTime}`);
            }
            modal.remove();
        };

        cancelBtn.onclick = () => modal.remove();
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };
    }
    function showDefaultModal() {
        const modal = document.createElement("div");
        modal.className = "time-picker-modal";
        modal.innerHTML = `
        <div class="time-picker-content">
            <h4> Load Existing Schedule</h4>
            <p> Do you want to load existing schedule data if available? </p>
            <div class="time-picker-buttons">
            <button id="DefaultCancel" class="border" >Cancel</button>
            <button id="DefaultConfirm" class="primary-btn">Load</button>
            </div>
        </div>
        `;
        document.body.appendChild(modal);

        const confirmBtn = modal.querySelector("#DefaultConfirm");
        const cancelBtn = modal.querySelector("#DefaultCancel");

        confirmBtn.onclick = () => {
            loadFromLocal();
            modal.remove();
        };

        cancelBtn.onclick = () => modal.remove();
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };
    }

    function convertToTimeInput(timeStr) {
        // Convert "8:00 AM" to "08:00"
        const match = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (match) {
            let hour = parseInt(match[1]);
            const minute = match[2];
            const period = match[3].toUpperCase();
            if (period === "PM" && hour !== 12) hour += 12;
            if (period === "AM" && hour === 12) hour = 0;
            return `${hour.toString().padStart(2, "0")}:${minute}`;
        }
        return "08:00";
    }

    function formatTimeFromInput(timeValue) {
        const [hour, minute] = timeValue.split(":");
        let hourNum = parseInt(hour);
        const period = hourNum >= 12 ? "PM" : "AM";
        let displayHour = hourNum % 12;
        if (displayHour === 0) displayHour = 12;
        return `${displayHour}:${minute} ${period}`;
    }

    // Update Read-Only Display Panel
    function updateDisplayPanel() {

        const summaryContainer = document.getElementById("scheduleSummary");
        if (!summaryContainer) return;

        let items = getItems();

        if (items.length === 0) {
            summaryContainer.innerHTML =
                '<div class = "text-center p-2">📭 No scheduled classes yet.</div>';
            return;
        }

        summaryContainer.className = "schedule-summary";
        if (isTeacher) {
            summaryContainer.innerHTML = `
                    ${items
                    .map(
                        (item) => `
                    <div class="summary-card">
                        <div class="summary-time">⏰ ${item.time}</div>
                        <div class="summary-day">📅 ${item.day}</div>
                        <div class="summary-text">${escapeHtml(item.text).replace(/\n/g, "<br>")}</div>
                    </div>
                    `,
                    )
                    .join("")}
                `;
        } else {

            let html = '';
            summaryContainer.innerHTML = '';
            
            items.forEach((courseItems, idx) => {
                let course = courseids.find(c => c.id == idx);
                if (courseItems.length === 0 || document.getElementById('course-'+idx)) return;
                html += ` <h6 class="my-3" id="course-${idx}">Course: ${course.fullname}</h6>
                <div class= "schedule-summary">
                            ${courseItems
                        .map(
                            (item) => `
                            <div class="summary-card">
                                <div class="summary-time">⏰ ${item.time}</div>
                                <div class="summary-day">📅 ${item.day}</div>
                                <div class="summary-text">${escapeHtml(item.text).replace(/\n/g, "<br>")}</div>
                            </div>
                            `,
                        )
                        .join("")}
                        </div>`;
            });

            let div = document.createElement("div");
            div.innerHTML = html;
            summaryContainer.parentElement.append(div);
        }
    }

    function getItems() {
        let items = [];
        if (!isTeacher && scheduleStudentData.length > 0) {

            scheduleStudentData.forEach((scheduleData, idx) => {
                if (!items[idx]) {
                    items[idx] = [];
                }
                for (let i = 0; i < scheduleData.length; i++) {
                    for (let j = 0; j < scheduleData[i].length; j++) {
                        const text = scheduleData[i][j].text;
                        if (text && text.trim() !== "") {
                            items[idx].push({
                                time: rowHeaders[i],
                                day: colHeaders[j],
                                text: text,
                            });
                        }
                    }
                }
            });
        } else {
            for (let i = 0; i < scheduleData.length; i++) {
                for (let j = 0; j < scheduleData[i].length; j++) {
                    const text = scheduleData[i][j].text;
                    if (text && text.trim() !== "") {
                        items.push({
                            time: rowHeaders[i],
                            day: colHeaders[j],
                            text: text,
                            row: i,
                            col: j,
                        });
                    }
                }
            }
        }
        return items;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function (m) {
            if (m === "&") return "&amp;";
            if (m === "<") return "&lt;";
            if (m === ">") return "&gt;";
            return m;
        });
    }

    function renderGrid() {
        const thead = document.getElementById("tableHeader");
        const tbody = document.getElementById("tableBody");
        if (!thead || !tbody) return;
        thead.innerHTML = "";
        tbody.innerHTML = "";

        let headerRow = document.createElement("tr");
        let thTime = document.createElement("th");
        thTime.innerText = "Time / Day";
        thTime.classList.add("time-col");
        thTime.style.position = "sticky";
        thTime.style.left = "0";
        headerRow.appendChild(thTime);

        for (let c = 0; c < colHeaders.length; c++) {
            let th = document.createElement("th");
            th.innerText = colHeaders[c];
            th.setAttribute("data-col", c);
            th.style.position = "sticky";
            th.style.top = "0";
            headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);

        let occupied = Array(rowHeaders.length)
            .fill()
            .map(() => Array(colHeaders.length).fill(false));
        for (let [key, merge] of mergedCells.entries()) {
            let [r, c] = key.split(",").map(Number);
            for (let i = 0; i < merge.rowSpan; i++)
                for (let j = 0; j < merge.colSpan; j++)
                    if (i !== 0 || j !== 0) occupied[r + i][c + j] = true;
        }

        for (let r = 0; r < rowHeaders.length; r++) {
            let tr = document.createElement("tr");
            let tdTime = document.createElement("td");
            tdTime.innerText = rowHeaders[r];
            tdTime.classList.add("time-col");
            tdTime.style.position = "sticky";
            tdTime.style.left = "0";
            tdTime.style.cursor = "pointer";
            tdTime.title = "Click to edit time";
            tdTime.onclick = (e) => {
                e.stopPropagation();
                showTimePicker(r);
            };
            tr.appendChild(tdTime);

            for (let c = 0; c < colHeaders.length; c++) {
                if (occupied[r][c]) continue;
                let mergeKey = findMasterMerge(r, c);
                if (mergeKey) {
                    let [mr, mc] = mergeKey.split(",").map(Number);
                    let mergeInfo = mergedCells.get(mergeKey);
                    if (mr === r && mc === c) {
                        let td = document.createElement("td");
                        td.setAttribute("data-row", r);
                        td.setAttribute("data-col", c);
                        td.rowSpan = mergeInfo.rowSpan;
                        td.colSpan = mergeInfo.colSpan;
                        td.classList.add("merged");
                        renderCellContent(td, scheduleData[mr][mc].text, mr, mc);
                        applyStyleToTd(td, scheduleData[mr][mc].style);
                        if (cellComments.has(`${mr},${mc}`))
                            addCommentBadge(td, cellComments.get(`${mr},${mc}`));
                        td.addEventListener("click", (e) => handleCellClick(mr, mc, e));
                        tr.appendChild(td);
                    }
                    continue;
                }
                let td = document.createElement("td");
                td.setAttribute("data-row", r);
                td.setAttribute("data-col", c);
                renderCellContent(td, scheduleData[r][c].text, r, c);
                applyStyleToTd(td, scheduleData[r][c].style);
                if (cellComments.has(`${r},${c}`))
                    addCommentBadge(td, cellComments.get(`${r},${c}`));
                td.addEventListener("click", (e) => handleCellClick(r, c, e));
                tr.appendChild(td);
            }
            tbody.appendChild(tr);
        }
        highlightSearch();
        updateDisplayPanel();
    }

    function findMasterMerge(row, col) {
        for (let [key, val] of mergedCells.entries()) {
            let [mr, mc] = key.split(",").map(Number);
            if (
                row >= mr &&
                row < mr + val.rowSpan &&
                col >= mc &&
                col < mc + val.colSpan
            )
                return key;
        }
        return null;
    }

    function renderCellContent(td, text, r, c) {
        td.innerHTML = "";
        let div = document.createElement("div");
        div.className = "cell-content";
        div.setAttribute("contenteditable", "true");
        div.innerText = text || "";
        div.addEventListener("input", (e) => {
            scheduleData[r][c].text = div.innerText;
            scheduleData[r][c].style = { ...scheduleData[r][c].style };
            pushUndoState();
            // autoSaveToLocal();
            updateDisplayPanel();
        });
        div.addEventListener("keydown", (e) => {
            if (e.ctrlKey && e.key === "b") {
                e.preventDefault();
                toggleBold(r, c);
            }
            if (e.ctrlKey && e.key === "i") {
                e.preventDefault();
                toggleItalic(r, c);
            }
        });
        td.appendChild(div);
        return td;
    }

    function applyStyleToTd(td, style) {
        if (style.bgColor) td.style.backgroundColor = style.bgColor;
        else td.style.backgroundColor = "";
        if (style.textColor) td.style.color = style.textColor;
        let div = td.querySelector(".cell-content");
        if (div) {
            if (style.fontWeight === "bold") div.style.fontWeight = "bold";
            else div.style.fontWeight = "normal";
            if (style.fontStyle === "italic") div.style.fontStyle = "italic";
            else div.style.fontStyle = "normal";
        }
    }

    function addCommentBadge(td, comment) {
        let badge = document.createElement("span");
        badge.className = "comment-badge";
        badge.innerText = "💬";
        badge.title = comment;
        td.appendChild(badge);
    }

    function toggleBold(r, c) {
        let style = scheduleData[r][c].style;
        style.fontWeight = style.fontWeight === "bold" ? "normal" : "bold";
        cellStyles.set(`${r},${c}`, { ...style });
        renderGrid();
        pushUndoState();
    }

    function toggleItalic(r, c) {
        let style = scheduleData[r][c].style;
        style.fontStyle = style.fontStyle === "italic" ? "normal" : "italic";
        cellStyles.set(`${r},${c}`, { ...style });
        renderGrid();
        pushUndoState();
    }

    function handleCellClick(r, c, e) {
        selectedRow = r;
        selectedCol = c;
        updateSelectionInfo();
    }

    function updateSelectionInfo() {
        const info = document.getElementById("selectionInfo");
        if (selectedRow >= 0 && selectedCol >= 0) {
            info.innerText = `📍 Selected: ${colHeaders[selectedCol]} @ ${rowHeaders[selectedRow]}`;
        } else {
            info.innerText = "";
        }
    }

    // Row/Column Operations
    function addRowAbove() {
        if (selectedRow >= 0) addRowAt(selectedRow);
        else addRowAt(0);
    }
    function addRowBelow() {
        if (selectedRow >= 0) addRowAt(selectedRow + 1);
        else addRowAt(rowHeaders.length);
    }
    function addRowAt(index) {
        let newTimeLabel = `${rowHeaders.length + 9}:00`;
        rowHeaders.splice(index, 0, newTimeLabel);
        let newRow = Array(colHeaders.length)
            .fill()
            .map(() => ({ text: "", style: {} }));
        scheduleData.splice(index, 0, newRow);
        renderGrid();
        pushUndoState();
    }
    function deleteSelectedRow() {
        if (selectedRow >= 0 && rowHeaders.length > 1) {
            rowHeaders.splice(selectedRow, 1);
            scheduleData.splice(selectedRow, 1);
            renderGrid();
            pushUndoState();
        }
    }
    function addColLeft() {
        if (selectedCol >= 0) addColAt(selectedCol);
        else addColAt(0);
    }
    function addColRight() {
        if (selectedCol >= 0) addColAt(selectedCol + 1);
        else addColAt(colHeaders.length);
    }
    function addColAt(idx) {
        let newDay = `Day${colHeaders.length + 1}`;
        colHeaders.splice(idx, 0, newDay);
        for (let row of scheduleData) row.splice(idx, 0, { text: "", style: {} });
        renderGrid();
        pushUndoState();
    }
    function deleteSelectedCol() {
        if (selectedCol >= 0 && colHeaders.length > 1) {
            colHeaders.splice(selectedCol, 1);
            for (let row of scheduleData) row.splice(selectedCol, 1);
            renderGrid();
            pushUndoState();
        }
    }
    function unmergeCell() {
        if (selectedRow >= 0 && selectedCol >= 0) {
            let key = findMasterMerge(selectedRow, selectedCol);
            if (key) mergedCells.delete(key);
            renderGrid();
            pushUndoState();
        }
    }

    function showContextMenu(e, r, c) {
        e.preventDefault();
        let menu = document.createElement("div");
        menu.className = "context-menu";
        menu.style.left = e.pageX + "px";
        menu.style.top = e.pageY + "px";
        let items = [
            "Clear Cell",
            "Pick BG Color",
            "Pick Text Color",
            "Add Comment",
            "Unmerge",
        ];
        items.forEach((action) => {
            let opt = document.createElement("div");
            opt.innerText = action;
            opt.onclick = () => {
                if (action === "Clear Cell") {
                    scheduleData[r][c].text = "";
                    renderGrid();
                    pushUndoState();
                    autoSaveToLocal();
                }
                if (action === "Pick BG Color") {
                    let color = prompt("Background color hex", "#f0f0ff");
                    if (color) {
                        scheduleData[r][c].style.bgColor = color;
                        cellStyles.set(`${r},${c}`, scheduleData[r][c].style);
                        renderGrid();
                    }
                }
                if (action === "Pick Text Color") {
                    let color = prompt("Text color hex", "#111111");
                    if (color) {
                        scheduleData[r][c].style.textColor = color;
                        cellStyles.set(`${r},${c}`, scheduleData[r][c].style);
                        renderGrid();
                    }
                }
                if (action === "Add Comment") {
                    let cmt = prompt("Comment note");
                    if (cmt) {
                        cellComments.set(`${r},${c}`, cmt);
                        renderGrid();
                        autoSaveToLocal();
                    }
                }
                if (action === "Unmerge") {
                    unmergeCell();
                }
                menu.remove();
            };
            menu.appendChild(opt);
        });
        document.body.appendChild(menu);
        setTimeout(() => {
            document.addEventListener("click", () => menu.remove(), { once: true });
        }, 0);
    }

    function highlightSearch() {
        let cells = document.querySelectorAll(".cell-content");
        cells.forEach((cell) => {
            cell.classList.remove("search-highlight");
            if (
                searchTerm &&
                cell.innerText.toLowerCase().includes(searchTerm.toLowerCase())
            ) {
                cell.classList.add("search-highlight");
            }
        });
    }

    function autoSaveToLocal() {
        try {
            let state = {
                rowHeaders,
                colHeaders,
                scheduleData,
                mergedCells: Array.from(mergedCells.entries()),
                cellComments: Array.from(cellComments.entries()),
                cellStyles: Array.from(cellStyles.entries()),
                weekMode,
            };

            let data = JSON.stringify(state);

            saveData([
                {
                    methodname: "block_cudb_save_schedule",
                    args: {
                        courseid: courseids[0]?.id,
                        content: data,
                    },
                },
            ])[0]
                .then((res) => {
                    updateStatus("Auto-saved");
                })
                .catch((e) => {
                    updateStatus("Storage error", e);
                });
        } catch (e) {
            updateStatus("Storage error", e);
        }
    }

    function loadFromLocal() {
        const promises = courseids.map(course => getSchedule(course.id));
        
        Promise.all(promises).then(() => {
            updateDisplayPanel(); // render ONLY once after all data loaded
        });
    }

    function getSchedule(courseid) {
        return getData([
            {
                methodname: "block_cudb_get_schedule",
                args: { courseid }
            },
        ])[0]
            .then((res) => {
                if (!res?.content) {
                    return;
                }
                let state = JSON.parse(res?.content);

                if (isTeacher) {
                    scheduleData = state.scheduleData;
                    rowHeaders = state.rowHeaders;
                    colHeaders = state.colHeaders;
                    mergedCells = new Map(state.mergedCells);
                    cellComments = new Map(state.cellComments);
                    cellStyles = new Map(state.cellStyles);
                    weekMode = state.weekMode;

                    renderGrid();
                    updateStatus("Loaded from storage");
                } else {
                    scheduleStudentData[courseid] = state.scheduleData;
                    updateStatus("Schedule loaded");
                }
            })
            .catch(() => {
                updateStatus("Corrupted data");
            });
    }

    function exportJSON() {
        let data = {
            rowHeaders,
            colHeaders,
            scheduleData,
            mergedCells: Array.from(mergedCells.entries()),
            cellComments: Array.from(cellComments.entries()),
            cellStyles: Array.from(cellStyles.entries()),
            weekMode,
        };
        let str = JSON.stringify(data);
        let a = document.createElement("a");
        a.href = "data:text/json," + encodeURIComponent(str);
        a.download = "schedule.json";
        a.click();
    }

    function importJSON(file) {
        let reader = new FileReader();
        reader.onload = (ev) => {
            try {
                let d = JSON.parse(ev.target.result);
                rowHeaders = d.rowHeaders;
                colHeaders = d.colHeaders;
                scheduleData = d.scheduleData;
                mergedCells = new Map(d.mergedCells);
                cellComments = new Map(d.cellComments);
                cellStyles = new Map(d.cellStyles);
                weekMode = d.weekMode;
                renderGrid();
                pushUndoState();
                updateStatus("Imported");
            } catch (e) {
                updateStatus("Invalid JSON");
            }
        };
        file && reader.readAsText(file);
    }

    function resetDefault() {
        weekMode = 5;
        initDefault();
    }
    function clearAll() {
        for (let i = 0; i < scheduleData.length; i++)
            for (let j = 0; j < scheduleData[0].length; j++)
                scheduleData[i][j].text = "";
        renderGrid();
        pushUndoState();
    }
    function toggleWeek() {
        weekMode = weekMode === 5 ? 7 : 5;
        initDefault();
    }
    function toggleDark() {
        darkMode = !darkMode;
        document.body.classList.toggle("dark", darkMode);
        renderGrid();
    }

    function pushUndoState() {
        undoStack.push(
            JSON.stringify({
                rowHeaders,
                colHeaders,
                scheduleData,
                mergedCells: Array.from(mergedCells.entries()),
                cellComments: Array.from(cellComments.entries()),
                cellStyles: Array.from(cellStyles.entries()),
            }),
        );
        if (undoStack.length > 30) undoStack.shift();
        redoStack = [];
    }
    function undo() {
        if (undoStack.length) {
            let prev = undoStack.pop();
            redoStack.push(
                JSON.stringify({
                    rowHeaders,
                    colHeaders,
                    scheduleData,
                    mergedCells: Array.from(mergedCells.entries()),
                }),
            );
            restoreState(JSON.parse(prev));
        }
    }
    function restoreState(st) {
        rowHeaders = st.rowHeaders;
        colHeaders = st.colHeaders;
        scheduleData = st.scheduleData;
        mergedCells = new Map(st.mergedCells);
        cellComments = new Map(st.cellComments || []);
        cellStyles = new Map(st.cellStyles || []);
        renderGrid();
        autoSaveToLocal();
    }

    function updateStatus(msg, e) {
        let statusMsg = document.getElementById("statusMsg");
        if (!statusMsg) {
            return;
        }
        statusMsg.innerText = msg;

        setTimeout(() => {
            if (document.getElementById("statusMsg").innerText === msg)
                document.getElementById("statusMsg").innerText = "✅ Ready";
        }, 2000);
    }
    if (isTeacher) {
        // Event Listeners
        document.addEventListener("keydown", (e) => {
            if (e.ctrlKey && e.key === "s") {
                e.preventDefault();
                autoSaveToLocal();
                updateStatus("Manual save");
            }
            if (e.ctrlKey && e.key === "z") {
                e.preventDefault();
                undo();
            }
        });
        document.getElementById("addRowAboveBtn").onclick = addRowAbove;
        document.getElementById("addRowBelowBtn").onclick = addRowBelow;
        document.getElementById("delRowBtn").onclick = deleteSelectedRow;
        document.getElementById("addColLeftBtn").onclick = addColLeft;
        document.getElementById("addColRightBtn").onclick = addColRight;
        document.getElementById("delColBtn").onclick = deleteSelectedCol;
        document.getElementById("saveManualBtn").onclick = () => autoSaveToLocal();
        document.getElementById("loadBtn").onclick = loadFromLocal;
        document.getElementById("exportJsonBtn").onclick = exportJSON;
        document.getElementById("importJsonBtn").onclick = () => {
            let inp = document.createElement("input");
            inp.type = "file";
            inp.accept = "application/json";
            inp.onchange = (e) => importJSON(inp.files[0]);
            inp.click();
        };
        document.getElementById("clearAllBtn").onclick = clearAll;
        document.getElementById("resetDefaultBtn").onclick = resetDefault;
        document.getElementById("weekToggleBtn").onclick = toggleWeek;
        document.getElementById("searchInput").addEventListener("input", (e) => {
            searchTerm = e.target.value;
            highlightSearch();
        });
        document.getElementById("clearSearchBtn").onclick = () => {
            document.getElementById("searchInput").value = "";
            searchTerm = "";
            highlightSearch();
        };
        document.addEventListener("contextmenu", (e) => {
            let td = e.target.closest("td");
            if (td && !td.classList.contains("time-col")) {
                let row = td.getAttribute("data-row"),
                    col = td.getAttribute("data-col");
                if (row !== null && col !== null)
                    showContextMenu(e, parseInt(row), parseInt(col));
            }
        });
        document.addEventListener("copy", (e) => {
            if (selectedRow >= 0 && selectedCol >= 0)
                e.clipboardData.setData(
                    "text/plain",
                    scheduleData[selectedRow][selectedCol].text,
                );
            e.preventDefault();
        });
        document.addEventListener("paste", (e) => {
            if (selectedRow >= 0 && selectedCol >= 0) {
                let txt = e.clipboardData.getData("text/plain");
                scheduleData[selectedRow][selectedCol].text = txt;
                renderGrid();
                pushUndoState();
                autoSaveToLocal();
            }
            e.preventDefault();
        });
    }
    initDefault();
};
