var phpunitdiffsPlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-phpunitdiffs-errors',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'PHPUnit',
    lastData: null,
    displayOnUpdate: false,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = ActiveBuild.registerQuery('phpunitdiffs-data', -1, {key: 'phpunitdiffs-data'})

        $(window).on('phpunitdiffs-data', function(data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                self.displayOnUpdate = true;
                query();
            }
        });

        $(document).on('click', '#phpunitdiffs-data .test-toggle', function(ev) {
            var input = $(ev.target);
            $('#phpunitdiffs-data tbody ' + input.data('target')).toggle(input.prop('checked'));
        });
    },

    render: function() {

        return $('<table class="table" id="phpunitdiffs-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>'+Lang.get('test')+'</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            $('#build-phpunitdiffs-errors').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var tests = this.lastData[0].meta_value;
        var thead = $('#phpunitdiffs-data thead tr');
        var tbody = $('#phpunitdiffs-data tbody');
        thead.empty().append('<th>'+Lang.get('test_message')+'</th>');
        tbody.empty();

        if (tests.length == 0) {
            $('#build-phpunitdiffs-errors').hide();
            return;
        }

        var counts = { success: 0, fail: 0, error: 0, skipped: 0, todo: 0 }, total = 0;

        for (var i in tests) {
            var severity = tests[i].severity || 'success',
                message = tests[i].message || ('<i>' + Lang.get('test_no_message') + '</i>');
            counts[severity]++;
            total++;
            tbody.append(
                '<tr class="'+  severity + '">' +
                '<td colspan="3">' +
                '<div>' + message + '</div>' +
                (tests[i].data ? '<div>' + this.repr(tests[i].data) + '</div>' : '') +
                '</td>' +
                '</tr>'
            );
        }

        var checkboxes = $('<th/>');
        thead.append(checkboxes).append('<th>' + Lang.get('test_total', total) + '</th>');

        for (var key in counts) {
            var count = counts[key];
            if(count > 0) {
                checkboxes.append(
                    '<div style="float:left" class="' + key + '"><input type="checkbox" class="test-toggle" data-target=".' + key + '" ' +
                    (key !== 'success' ? ' checked' : '') + '/>&nbsp;' +
                    Lang.get('test_'+key, count)+ '</div> '
                );
            }
        }

        tbody.find('.success').hide();

        $('#build-phpunitdiffs-errors').show();
    },

    repr: function(data)
    {
        switch(typeof(data)) {
            case 'boolean':
                return '<span class="boolean">' + (data ? 'true' : 'false') + '</span>';
            case 'string':
                return '<span class="string">"' + data + '"</span>';
            case 'undefined': case null:
            return '<span class="null">null</span>';
            case 'object':
                var rows = [];
                if(data instanceof Array) {
                    for(var i in data) {
                        rows.push('<tr><td colspan="3">' + this.repr(data[i]) + ',</td></tr>');
                    }
                } else {
                    for(var key in data) {
                        rows.push(
                            '<tr>' +
                            '<td>' + this.repr(key) + '</td>' +
                            '<td>=&gt;</td>' +
                            '<td>' + this.repr(data[key]) + ',</td>' +
                            '</tr>');
                    }
                }
                return '<table>' +
                    '<tr><th colspan="3">array(</th></tr>' +
                    rows.join('') +
                    '<tr><th colspan="3">)</th></tr>' +
                    '</table>';
        }
        return '???';
    }
});

ActiveBuild.registerPlugin(new phpunitdiffsPlugin());
