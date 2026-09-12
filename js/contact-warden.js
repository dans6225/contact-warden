(function () {
    'use strict';

    var script = document.currentScript;
    var initUrl = script.getAttribute('data-init-url') || 'init.php';
    var form = script.getAttribute('data-form-id')
        ? document.getElementById(script.getAttribute('data-form-id'))
        : document.getElementById('cw-form');

    if (!form) {
        return;
    }

    var tokenField = form.querySelector('input[name="_cw_token"]');
    var interactionField = form.querySelector('input[name="_interaction_count"]');
    var interactionCount = 0;

    var fields = form.querySelectorAll('.cw-field');
    for (var i = 0; i < fields.length; i++) {
        (function (el) {
            if (el === interactionField) {
                return;
            }
            el.addEventListener('input', function () {
                interactionCount++;
                if (interactionField) {
                    interactionField.value = String(interactionCount);
                }
            });
        })(fields[i]);
    }

    fetch(initUrl, { credentials: 'same-origin' })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (tokenField && data.token) {
                tokenField.value = data.token;
            }
            if (data.fieldMap) {
                var mappable = form.querySelectorAll('.cw-field');
                for (var j = 0; j < mappable.length; j++) {
                    var el = mappable[j];
                    var randomized = data.fieldMap[el.name];
                    if (randomized) {
                        el.name = randomized;
                    }
                }
            }
        })
        .catch(function () {
            // Init failed (blocked request, offline, etc.) — the form still
            // submits with its default field names and no token; the server
            // treats that the same as a no-JS submission.
        });
})();
