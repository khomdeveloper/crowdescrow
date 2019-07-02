var Units = {
	//multiplacer
	host: document.body,
	templates: {
		ball: '<div class="pa round" style="background:red;" id="{{id}}"></div>'
	},
	types: false,
	list: {},
	place: function(p) {

		if (p.id && this.list[p.id]) {//unit already present
			var unit = this.list[p.id];
			if (p.refresh) {
				unit.remove();
				this.list[p.id] = null;
			} else {
				unit.show();
				return unit;
			}
		}
		var unit = new Unit(p);
		this.list[unit.id] = unit;
		return unit;
	},
	get: function(id) {
		return this.list[id];
	},
	getMyRocket: function() {
		//console.log(this.types, this.list);
		for (var i in this.list) {
			//console.log(this.list[i].type, this.types[this.list[i].type*1]);
			if (this.types[this.list[i].type * 1].type == 'rocket') {  //TODO: and it`s mine
				//console.log(this.list[i]);
				return this.list[i];
			}
		}
	},
	//вывод всех движущихся юнитов
	outAll: function(data) {

		console.log(data);

		if (Client.ignoreResponse) { //??? зачем это нужно
			console.log('response ignored');
			return false;
		} else {
			console.log('outAll');
		}

		var that = this;

		//console.log(data);

		that.types = data.unittypes;

		if (data && data.units) {
			var mark_data = [];
			for (var i in data.units) {
				var record = data.units[i];
				record['host'] = $('.main_field');

				for (var j in record.trajectories) {
					var trajectory = record.trajectories[j];
					mark_data.push({
						x: trajectory.x0,
						y: trajectory.y0,
						color: j == 0
								? 'white'
								: 'silver'
					});
				}

				mark_data.push({
					x: record.x,
					y: record.y,
					color: 'red'
				});

				that.mark(mark_data);//place marks


				if (!window.transform2Dloaded) {
					B.loadScript(A.baseURL() + 'js_minified/translate2Dmin.js', function() {
						return that.outAll(data);
					});
					return;
				}

				Units.place(record).set(record).out(); //place unit
			}
		}
	},
	mark: function(markers) {

		for (var i in markers) {
			var marker = markers[i];
			Base.appendOnce({
				host: $('.main_field'),
				html: '<div class="marker' + i + ' pa" style="left:0px; top:0px; border-left:1px solid ' + (marker.color
						? marker.color
						: 'red') + '; border-top: 1px solid ' + (marker.color
						? marker.color
						: 'red') + '; display:none; width:5px; height:5px; z-index:1;"></div>',
				selector: '.marker' + i
			}).css({
				left: marker.x + 'px',
				top: marker.y + 'px'
			}).show();
		}

		return this;
	}
};

//TODO json to html parser

var Unit = function(p) {

	this.interrupted = false;

	//calculate x,y depends on trajectory data and time
	this.calculate = function() {

		var client_time = Date.now() / 1000;
		var contact_time = this.t_next - Client.time_offset

		if (this.interrupted) { //if interrupted - stop and wait
			return this;
		}


		if (client_time < contact_time) {

			var dt = contact_time - client_time;

			var dx = this.vx * dt;
			var dy = this.vy * dt;

			var current_trajectory = this.trajectories[0];

			if (current_trajectory.begin * 1 != this.t_next) {
				console.error('Wrong trajectory', this);
			}

			this.x = current_trajectory.x0 - dx;
			this.y = current_trajectory.y0 - dy;

			//console.log(this.x, this.y);
		} else {//object already on next trajectory

			var choosen = false;
			for (var i in this.trajectories) {

				var trajectory = this.trajectories[i];

				var begin = trajectory.begin - Client.time_offset;
				var expired = trajectory.expired - Client.time_offset;

				if (client_time >= begin && client_time < expired) {

					var dt = client_time - begin;

					this.x = trajectory.x0 * 1 + trajectory.vx * dt;
					this.y = trajectory.y0 * 1 + trajectory.vy * dt;

					//console.log(trajectory.vx, dt, trajectory.x0, this.x);

					choosen = true;
					break;
				}

			}

			if (!choosen) {
				console.error('Trajectory is not available', this.trajectories, client_time, contact_time, this.t_next);
			} else {
				//console.log(this.trajectories);
			}

		}

		return this;

	}
	this.move = function() {
		var that = this;
		setTimeout(function() {
			that.calculate().out().move();
		}, 50);
	}

	this.hide = function() {
		this.$.hide();
	};

	this.show = function() {
		this.$.show();
	};

	this.remove = function() {
		this.$.remove();
	};

	this.command = function(com) {
		if (com === 'stop') {
			this.vx = 0.0000001;
			this.vy = 0;
			var changed = true;
		}
		//NOTE: no need to change anything if new speed is the same
		//TODO: get available speed amount from server
		if (com === 'left' && (this.vx > 0 || this.vx < 0.001)) {
			this.vx = 0.0000001; //get possible speed value
			var changed = true;
		}
		if (com === 'right' && (this.vx < 0 || this.vx < 0.001)) {
			this.vx = 0.0000001;
			var changed = true;
		}
		if (changed) {
			this.interrupted = Date.now() / 1000;
		}
	}

	//place unit on the screen
	this.place = function(p) {

		if (!p.id) {
			console.error('Unit id required');
			return false;
		}

		this.host = p.host
				? p.host
				: $('.main_field');
		this.id = p.id;

		this.w = p.w
				? p.w
				: 10;
		this.h = p.h
				? p.h
				: 10;

		//x = x0
		this.x = p.x * 1;
		this.y = p.y * 1;

		this.$ = B.appendOnce({
			host: this.host,
			html: '<div class="pa round unit_' + this.id + '" style="background:' + (Units.types[p.type].type == 'ball'
					? 'white'
					: 'red') + '; left:0px; top:0px; width:10px; height:10px; display:none; border:1px solid black; z-index:0; overflow:visible;"></div>',
			selector: '.unit_' + this.id
		}).css({
			width: this.w + 'px',
			height: this.h + 'px'
		});

		if (p.parts) {
			for (var j in p.parts) {
				B.appendOnce({
					host: this.$,
					html: '<div class="pa round part_' + this.id + '_' + j + '" style="background:' + (Units.types[p.type].type == 'ball'
							? 'white'
							: 'red') + '; left:0px; top:0px; width:10px; height:10px; border:1px solid black; z-index:0;"></div>',
					selector: '.part_' + this.id + '_' + j
				}).css({
					width: this.w + 'px',
					height: this.h + 'px',
					left: p.parts[j].x + 'px',
					top: p.parts[j].y + 'px'
				});
			}
		}

		this.move();
	};

	this.set = function(what) {
		for (var field in what) {
			if (field === 'x' || field === 'y') {
				this[field + '0'] = what[field];
			} else {
				this[field] = what[field];
			}
		}
		this.interrupted = false;
		return this;
	};

	//output unit on the screen
	this.out = function() {

		//for the future change this option to translate
		var that = this;
		var str = 'translateX(' + (this.x - this.w / 2) + 'px) translateY(' + (this.y - this.h / 2) + 'px)';

		A.w(['$', 'transform2Dloaded'], function() {
			that.$.css({
				transform: str
			}).fadeIn();
		});

		/*
		 this.$.css({
		 left: Math.round(this.x - this.w / 2, 0) + 'px',
		 top: Math.round(this.y - this.h / 2, 0) + 'px'
		 }).fadeIn();*/

		return this;
	}

	this.place(p);
};

