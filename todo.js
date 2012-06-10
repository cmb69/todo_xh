(function($) {

    Todo = {
    
        sel: function(name) {
            return $.makeArray($('#todo_grid_' + name + ' .trSelected').map(function() {
                return $(this).attr('id').substr(3)
            }))
        },
    
        add: function(page, name) {
            var dlg = $('#todo_edit');
            dlg.dialog('option', 'buttons', {
                "Cancel": function() {dlg.dialog('close')},
                "Ok": function() {
                    Todo.post(page, name, null);
                    dlg.dialog('close')
                }
            });
            dlg.dialog({title: 'New Task'});
            var flds = ['task', 'link', 'notes', 'resp', 'state', 'date'];
            for (var i = 0; i < flds.length; i++) {
                dlg.find('[name="todo_' + flds[i] + '"]').val('');
            }
            dlg.dialog('open')
        },
        
        edit: function(page, name) {
            var dlg = $('#todo_edit');
            var ids = Todo.sel(name);
            if (ids.length < 1) {
                alert('Select an entry to edit!');
                return;
            }
            var id = ids[0];
            dlg.dialog('option', 'buttons', {
                "Cancel": function() {dlg.dialog('close')},
                "Ok": function() {
                    Todo.post(page, name, id);
                    dlg.dialog('close')
                }
            });
            dlg.dialog({title: 'Task ' + id});
            $.get('?' + page + '&todo_name=' + name + '&todo_act=get&todo_id=' + id, '', function(data) {
                rec = eval('(' + data + ')');
                for (prop in rec) {
                    dlg.find('[name="todo_' + prop + '"]').val(rec[prop]);
                }
                dlg.dialog('open')
            });
        },
        
        post: function(page, name, id) {
            var dlg = $('#todo_edit');
            $.post('?' + page + '&todo_name=' + name + '&todo_act=post' + (id != null ? '&todo_id=' + id : ''),
                    $('#todo_edit').serialize(), function(d) {console.log(d)});
            $('#todo_grid_' + name).flexReload();
        },
        
        remove: function(page, name) {
            $.post('?' + page + '&todo_name=' + name + '&todo_act=delete', {'todo_ids[]': Todo.sel(name)}, function(d) {console.log(d)});
            $('#todo_grid_' + name).flexReload()
        },
        
        
        move: function(page, name) {
            var dlg = $('#todo_move');
            dlg.dialog('option', 'buttons', {
                "Cancel": function() {$(this).dialog('close')},
                "Ok": function() {
                    var dname = $(this).find('select').val();
                    $.post('?' + page + '&todo_name=' + name + '&todo_act=move',
                            {'todo_ids[]': Todo.sel(name), 'todo_dest': dname}, function(d) {console.log(d)});
                    $(this).dialog('close');
                    $('#todo_grid_' + name).flexReload();
                    $('#todo_grid_' + dname).flexReload();
                }
            });
            dlg.dialog('open')
        },
        
        init: function(page, name) {
            var btns = !Todo.isMember ? null : [
                {name: 'Add', bclass: 'todo_btn_add', onpress: function() {Todo.add(page, name)}},
                {name: 'Edit', bclass: 'todo_btn_edit', onpress: function() {Todo.edit(page, name)}},
                {name: 'Remove', bclass: 'todo_btn_remove', onpress: function() {Todo.remove(page, name)}},
                {name: 'Move', bclass: 'todo_btn_move', onpress: function() {Todo.move(page, name)}}
            ];
            $('#todo_grid_' + name).flexigrid({
                url: '?' + page + '&todo_name=' + name + '&todo_act=list',
                method: 'GET',
                dataType: 'json',
                colModel: [
                    {display: 'Task', name: 'task', width: 128, sortable: true, align: 'left'},
                    {display: 'Link', name: 'link', width: 64, sortable: true, align: 'left'},
                    {display: 'Notes', name: 'notes', width: 256, sortable: true, align: 'left'},
                    {display: 'Responsible', name: 'resp', width: 64, sortable: true, align: 'left'},
                    {display: 'State', name: 'state', width: 64, sortable: true, align: 'left'},
                    {display: 'Date', name: 'date', width: 64, sortable: true, align: 'left'}
                ],
                buttons: btns,
                searchitems: [
                    {display: 'Task', name: 'task'},
                    {display: 'Notes', name: 'notes', isdefault: true}
                ],
                sortname: 'task',
                sortorder: 'asc',
                usepager: true
            })
        }
    
    }

    $(function() {
        var dlg = $('#todo_edit');
        dlg.find('[name="todo_date"]').datepicker({dateFormat: 'yy-mm-dd'});
        dlg.dialog({autoOpen: false, modal: true, width: 536});
        
        var dlg = $('#todo_move');
        dlg.dialog({autoOpen: false, modal: true})
    })

})(jQuery)
