/*      
 id
 host
 totalRecords
 recordsAtPage 
 maxPage
 maxPageAction : function(){ <- if this page is limit
 
 }
 currentPage (current page)
 
 */

var Pagers = {
    list: {},
    place: function(p) {
	var id = p.id || 'pager_' + Math.round(Math.random());
	if (!this.list[id]) { //if not - create	
	    //console.log('created');
	    p.id = id;
	    this.list[id] = new Pager(p);
	} else {
	    if (!p.id) { //restrat if automatic id present
		this.place(p); //once again
		return false;
	    }
	}
	//console.log('to set',p);
	return this.list[id].set(p);
    }
};

var Pager = function(p) {

    this.create = function(p) {

	//console.log(p);

	if (!p.id) {
	    console.error('Need id when create pager!');
	}
	this.id = p.id;
	this.host = p.host || $(document.body);

	var that = this;

	if (this.totalRecords <= this.recordsAtPage) { //no need to create pager if it is less than 3
	    $('.point_pager', this.host).remove();
	    return;
	}

	if (!$('.point_pager', this.host).length) {//only if there is no pager here
	    this.host.append('<div class="ac point_pager"></div>');
	}

    }; //of create

    this.set = function(p) {

	//console.log('set',p);

	if (p.totalRecords <= p.recordsAtPage) {
	    $('.point_pager', this.host).hide();
	    return;
	} else {
	    $('.point_pager', this.host).show();
	}

	this.maxPage = p.maxPage || false;
	this.maxPageAction = p.maxPageAction || false;
	this.callback = p.callback;

	this.recordsAtPage = p.recordsAtPage || 3;
	this.currentPage = p.currentPage || 0;

	this.totalRecords = this.maxPage
		? Math.min(p.totalRecords || 0, this.maxPage * this.recordsAtPage)
		: p.totalRecords || 0

	//calculate necessary pages
	var pages = Math.ceil(this.totalRecords / this.recordsAtPage);
	var h = [];

	console.log(this);

	if (this.maxPage && pages === 1) {
	    
	} else {
	    for (var i = 0; i < pages; i++) {
		h.push('<div class="empty_dot_pager select_page_' + i + '"></div>');
	    }
	}


	//add action triangle
	if (this.maxPage) {
	    h.push('<div class="triangle_host"><div class="select_page_other"><div class="arrow-down-red"></div><div class="arrow-down-gold"></div></div></div>');
	}

	//place pages in pager
	$('.point_pager', this.host).html(h.join(''));

	//set up current page
	$('.select_page_' + this.currentPage, this.host).css({
	    'background-color': 'rgb(196,43,44)'
	});

	var that = this;
	if (this.maxPage && this.maxPageAction) {
	    $('.select_page_other', this.host).unbind('mouseover').mouseover(function() {
		$('.arrow-down-gold', this.host).hide();
	    }).unbind('mouseout').mouseout(function() {
		$('.arrow-down-gold', this.host).show();
	    }).unbind('mousedown').mousedown(function() {
		Base.press($(this));
		that.maxPageAction();
	    });
	}

	//set controllers
	$('.empty_dot_pager', that.host).unbind('mousedown').mousedown(function() {
	    var page = $(this).attr('class').split('select_page_')[1] * 1;
	    if (page * 1 !== that.currentPage * 1) {
		$('.empty_dot_pager', that.host).css({
		    background: 'none'
		});
		$(this).css({
		    'background-color': 'rgb(196,43,44)'
		});

		var slide = that.currentPage > page
			? 'right'
			: 'left';

		that.currentPage = page;

		if (that.callback) {
		    that.callback(page, slide);
		}
	    }
	});

	return that;
    };

    this.create(p);

};