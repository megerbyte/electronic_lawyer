<?php
// ... Your existing PHP session code unchanged ...
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= isset($title) ? $title : 'Mindmap' ?></title>
    <style>
        /* ... (Your existing styles) ... */
        .edit-controls { margin-left: 15px; }
        .edit-controls button { font-size: 0.95em; margin-right: 6px; }
        .editing { background: #e3f2fd !important; }
    </style>
</head>
<body>
<?php
// ... Your existing PHP session block ...
?>
<div id="mindmap-container">
    <!-- Your generated nodes (unchanged) -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add edit controls to each node
    document.querySelectorAll('.node-content').forEach(function(content) {
        let nodeDiv = content.closest('.node');
        let nodeId = nodeDiv.getAttribute('data-node-id');
        let controls = document.createElement('span');
        controls.className = 'edit-controls';

        let editBtn = document.createElement('button');
        editBtn.textContent = 'Edit';
        editBtn.onclick = function() { enableEdit(nodeDiv); };
        controls.appendChild(editBtn);

        let addBtn = document.createElement('button');
        addBtn.textContent = 'Add Child';
        addBtn.onclick = function() { addChildNode(nodeDiv); };
        controls.appendChild(addBtn);

        let delBtn = document.createElement('button');
        delBtn.textContent = 'Delete';
        delBtn.onclick = function() { deleteNode(nodeDiv); };
        controls.appendChild(delBtn);

        content.appendChild(controls);
    });

    // Enable editing of question text
    window.enableEdit = function(nodeDiv) {
        let qtext = nodeDiv.querySelector('.question-text');
        if (!qtext || qtext.classList.contains('editing')) return;
        let oldText = qtext.textContent;
        qtext.classList.add('editing');
        let input = document.createElement('input');
        input.type = 'text';
        input.value = oldText;
        input.style = 'font-size:1em;width:65%';
        qtext.textContent = '';
        qtext.appendChild(input);
        input.focus();
        input.onblur = function() {
            qtext.textContent = input.value;
            qtext.classList.remove('editing');
        };
        input.onkeydown = function(e) {
            if (e.key === 'Enter') {
                input.blur();
            }
        };
    };

    // Add child node
    window.addChildNode = function(parentDiv) {
        let parentId = parentDiv.getAttribute('data-node-id');
        let childId = prompt("New child node ID:");
        if (!childId) return;
        let childText = prompt("Question text for new node:");
        if (!childText) return;
        let level = parseInt(parentDiv.getAttribute('data-level') || '0') + 1;
        let childNode = document.createElement('div');
        childNode.className = 'node';
        childNode.setAttribute('data-node-id', childId);
        childNode.setAttribute('data-level', level);
        childNode.setAttribute('data-answer-type', 'yes');
        childNode.setAttribute('data-parent-id', parentId);
        childNode.setAttribute('data-terminal', 'true');
        childNode.style = 'display:block;';

        let contentDiv = document.createElement('div');
        contentDiv.className = 'node-content';
        let spanQ = document.createElement('span');
        spanQ.className = 'question-text';
        spanQ.textContent = childText;
        contentDiv.appendChild(spanQ);

        // Add controls to new node
        let controls = document.createElement('span');
        controls.className = 'edit-controls';
        let editBtn = document.createElement('button');
        editBtn.textContent = 'Edit';
        editBtn.onclick = function() { enableEdit(childNode); };
        controls.appendChild(editBtn);
        let addBtn = document.createElement('button');
        addBtn.textContent = 'Add Child';
        addBtn.onclick = function() { addChildNode(childNode); };
        controls.appendChild(addBtn);
        let delBtn = document.createElement('button');
        delBtn.textContent = 'Delete';
        delBtn.onclick = function() { deleteNode(childNode); };
        controls.appendChild(delBtn);
        contentDiv.appendChild(controls);

        let actionBtns = document.createElement('span');
        actionBtns.className = 'action-buttons';
        let yesBtn = document.createElement('button');
        yesBtn.className = 'yes-button';
        yesBtn.textContent = 'Yes';
        yesBtn.setAttribute('data-node-id', childId);
        actionBtns.appendChild(yesBtn);
        let noBtn = document.createElement('button');
        noBtn.className = 'no-button';
        noBtn.textContent = 'No';
        noBtn.setAttribute('data-node-id', childId);
        actionBtns.appendChild(noBtn);
        contentDiv.appendChild(actionBtns);

        let noteDiv = document.createElement('div');
        noteDiv.className = 'note-content';
        noteDiv.style = 'display:none;';
        contentDiv.appendChild(noteDiv);

        let textarea = document.createElement('textarea');
        textarea.className = 'comment-textbox';
        textarea.id = `textbox-${childId}`;
        textarea.placeholder = 'Leave comments or questions here';
        textarea.rows = 2;
        textarea.style = 'resize:vertical;min-height:42px;max-width:75vw;width:75%;display:block;box-sizing:border-box;';
        contentDiv.appendChild(textarea);

        childNode.appendChild(contentDiv);

        let childNodesDiv = document.createElement('div');
        childNodesDiv.className = 'child-nodes';
        childNodesDiv.setAttribute('data-parent-id', childId);
        childNodesDiv.style = 'display:none;';
        childNode.appendChild(childNodesDiv);

        // Find child-nodes container of parent
        let childContainer = parentDiv.querySelector('.child-nodes');
        if (!childContainer) {
            childContainer = document.createElement('div');
            childContainer.className = 'child-nodes';
            childContainer.setAttribute('data-parent-id', parentId);
            childContainer.style = 'display:block;';
            parentDiv.appendChild(childContainer);
        }
        childContainer.appendChild(childNode);
        childContainer.style.display = 'block';
    };

    // Delete node
    window.deleteNode = function(nodeDiv) {
        if (confirm("Delete this node and all its children?")) {
            nodeDiv.remove();
        }
    };

    // Download mindmap as HTML
    window.downloadMindmap = function() {
        let container = document.getElementById('mindmap-container');
        let html = container.innerHTML;
        let blob = new Blob([html], {type: 'text/html'});
        let a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = "edited_mindmap.html";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // Add download button
    let dlBtn = document.createElement('button');
    dlBtn.textContent = "Download Mindmap";
    dlBtn.onclick = downloadMindmap;
    dlBtn.style = "position:fixed;top:18px;right:18px;z-index:999;font-size:1.1em;padding:8px 18px;background:#2196f3;color:#fff;border:none;border-radius:6px;cursor:pointer;box-shadow:0 2px 8px #0002;";
    document.body.appendChild(dlBtn);

    // ... (Your original questionnaire logic and listeners can remain unchanged) ...
});
</script>
</body>
</html>