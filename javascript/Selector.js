/**
 * Selector
 */


/**
 * p = {
 *	id : selector_id
 *	host: host where to place
 *	
 *	options : {
 *	    key : HTML,
 *	    img
 *	}
 *	
 *	this.markers = p.markers || {
 opened: '▲',
 closed: '▼',
 image: 'images/t.gif' //default image
 }
 
 selected
 *	
 *   }
 *
 */

var Selector = function(p) {

    this.hide = function() {
	this.$.hide();
    };

    this.show = function() {
	this.$.show();
    };

    this.remove = function() {
	this.$.remove();
    };

    this.place = function(p) {
	var id = p.id || Math.round(Math.random() * 10000);
	this.host = p.host || $(document.body);
	this.id = id;

	this.action = p.action
		? p.action
		: function(id) {
		    console.log(id);
		};

	var that = this;

	if ($('.selector_' + id).length) {
	    if (p.id) {
		console.error('Object with ' + id + ' already present');
		return false;
	    }
	    that.place(p); //this if generated id already present
	    return false;
	}

	this.selected = p.selected || false;

	this.preselect = p.preselect;
	
	this.default = p.default || 'Selector';
	this.markers = p.markers || {
	    opened: '▲',
	    closed: '▼',
	    image: 'images/t.gif' //default image
	}

	this.options = p.options;

	var h = [];
	for (var key in this.options) {
	    if (this.options[key].text) {
		h.push('<table class="w100 cp select_option option_' + key + '"><tr><td class="value">' + this.options[key].text + '</td>' + (
			this.options[key].img
			? '<td style="width:25px;"><img style="width:25px;" src="' + this.options[key].img + '" alt="' + key + '"/></td>'
			: ''
			) + '</tr></table>');
	    }
	}

	this.$ = Base.appendOnce({
	    host: this.host,
	    selector: '.selector_' + id,
	    html: '<div class="selector selector_' + this.id + '">' +
		    '<table class="current"><tr><td><span class="selected_title">' + this.default + '</span> <span class="marker">' + this.markers.closed + '</span></td>' +
		    '<td style="width:25px;"><img class="selected_image" style="width:25px;" src="' + this.markers.image + '" alt/></td>' +
		    '</tr></table>' +
		    (this.options
			    ? '<div class="options invisible rc shadow" style="background:white;">' + h.join('') + '</div>'
			    : '') +
		    '</div>'
	});

	this.initiator = $('.current', this.$);

	this.status = 'closed';

	var that = this;
	this.initiator.unbind('mousedown').mousedown(function() {
	    if ($('.options', that.$).is(':visible')) {
		that.close();
	    } else {
		that.open();
	    }
	});

	$('.select_option', that.$).unbind('mousedown').mousedown(function() {
	    var id = $(this).attr('class').split('select_option option_')[1];
	    that.select(id);
	});

    };

    this.open = function() {
	$('.options', this.$).slideDown();
	this.status = 'opened';
	$('.marker', this.$).html(this.markers[this.status]);
    };

    this.close = function(callback) {
	$('.options', this.$).slideUp(function() {
	    if (callback) {
		callback();
	    }
	});
	this.status = 'closed';
	$('.marker', this.$).html(this.markers[this.status]);
    }

    this.select = function(id) {

	var that = this;

	if (this.options[id]) {
	    var changed = false;
	    if (this.selected != id) {
		this.selected = id;
		if (this.options[id].img) {
		    $('.selected_image', this.$).attr('src', this.options[id].img);
		} else {
		    $('.selected_title', this.$).html(this.options[id].text);
		}
		changed = true;
	    } else {
		
	    }
	    that.close(function() {
		if (that.action && changed && id != that.preselect) {
		    that.action(id);
		}
	    });
	} else {//no variant has founded in options
	    //TODO:
	}

    };

    this.place(p);

    this.select(p.preselect);

};