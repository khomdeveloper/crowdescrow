var CheckBoxes = {//factory method
    list: {},
    place: function(p) {
	var but = new CheckBox(p);
	this.list[but.id] = but;
    }
};

var CheckBox = function(p) {



    this.place = function(p) {
	var id = p.id || Math.round(Math.random() * 10000);

	this.host = p.host || document.body;

	this.id = id;

	this.status = p.status * 1 || 0;

	var that = this;

	if ($('.checkbox_box_' + id).length) {
	    if (p.id) {
		console.error('Object with ' + id + ' already present');
		return false;
	    }
	    that.place(p);
	    return false;
	}

	var obj = $('.raw_checkbox').clone();

	$('.checkbox_box_raw', obj).removeClass('checkbox_box_raw').addClass('checkbox_' + id).addClass('checkbox_box').attr({
	    id: 'checkbox_' + id
	});
	$('.check_box_title_raw', obj).removeClass('checkbox_title_raw').addClass('checkbox_title_' + id).addClass('checkbox_title');
	$('.raw_checkbox_var', obj).removeClass('raw_checkbox_var').addClass('checkbox_var_' + id).attr({
	    name: 'checkbox_' + id,
	    value: this.status
	});

	this.v = $('.checkbox_var_' + id);

	obj.removeClass('raw_checkbox').addClass('checkbox_host_' + id).addClass('checkbox_host');

	if (p.cls) {
	    obj.addClass(p.cls);
	}

	if (p.prepend) {
	    obj.prependTo(this.host);
	} else {
	    obj.appendTo(this.host);
	}

	obj.css({
	    display: 'inline-block'
	}).show();

	this.$ = $('.checkbox_host_' + id);

	$('.checkbox_title_' + id).html(p.title);

	//add css 

	if (p.css) {
	    this.$.css(p.css);
	}

	if (p.class) {
	    if (p.class.join) {
		this.$.addClass(p.class.join(' '));
	    } else {
		this.$.addClass(p.class);
	    }
	}

	//set controller

	$('.checkbox_' + id, this.$).unbind('mousedown').mousedown(function() {

	    var id = $(this).attr('id').split('checkbox_')[1];
	    var obj = CheckBoxes.list[id];

	    if (obj.status) {
		$('.checkbox_' + id).html('');
		obj.status = 0;
	    } else {
		$('.checkbox_' + id).html('✓');
		obj.status = 1;
	    }
	    obj.v.attr('value', obj.status);
	    if (p.action) {
		p.action(obj.status);
	    }
	});

    };

    this.setStatus = function(status) {
	if (this.status != status) {
	    if (status == 1) {
		$('.checkbox_' + this.id).html('✓');
	    } else {
		$('.checkbox_' + this.id).html('');
	    }
	    this.status = status == 0
		    ? 0
		    : 1;
	    this.v.attr('value', this.status);
	}
    };

    this.place(p);

};