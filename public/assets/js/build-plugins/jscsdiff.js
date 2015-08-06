var jscsdiffPlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-jscsdiff',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'JSCSdiff',
    lastData: null,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = ActiveBuild.registerQuery('jscsdiff-data', -1, {key: 'jscsdiff-data'})

        $(window).on('jscsdiff-data', function(data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                query();
            }
        });
    },

    render: function() {
        return $('<div class="table-responsive"><table class="table" id="jscsdiff-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>name</th>' +
            '   <th>message</th>' +
            '   <th>line</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table></div>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            $('#build-jscsdiff').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].meta_value;
        var tbody = $('#jscsdiff-data tbody');
        tbody.empty();

        if (errors.length == 0) {
            $('#build-jscsdiff').hide();
            return;
        }

        console.log(errors);
        for (var i in errors) {
            var file = errors[i].name;

            if (ActiveBuild.fileLinkTemplate) {
                var fileLink = ActiveBuild.fileLinkTemplate.replace('{FILE}', file);
                fileLink = fileLink.replace('{LINE}', errors[i].line);

                file = '<a target="_blank" href="'+fileLink+'">' + file + '</a>';
            }

            //console.log(errors[i].name);
            //console.log(errors[i]);
            var row = $('<tr>' + '<td>'+file+'</td><td>'+errors[i].msg+'</td><td>'+errors[i].line+'</td></tr>');

                row.addClass('danger');

            tbody.append(row);
        }

        $('#build-jscsdiff').show();
    }
});

ActiveBuild.registerPlugin(new jscsdiffPlugin());
