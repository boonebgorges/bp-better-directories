window.wp = window.wp || {};

( function( $ ) {
	$( document ).ready( function() {

		/**
		 * Member model.
		 */
		var BpbdMember = Backbone.Model.extend( {
			initialize: function( singleData ) {
				if ( 'undefined' === typeof singleData ) {
					return;
				}

				this.set( {
					'name': singleData.name,
					'emailhash': singleData.emailhash,
					'twitterurl': singleData.twitterurl,
					'twittertxt': singleData.twittertxt,
					'atendeeUrl': singleData.atendeeUrl
				} );
			}
		} ),

		/**
		 * Collection of members
		 */
		BpbdMemberCollection = Backbone.Collection.extend( {
			columnNames: null,
			filters: null,
			model: BpbdMember,

			setup: function( args ){
				this.columnNames = args;
			},

			searchFor: function( filters ) {
				var self = this;
				self.filters = filters;
				this.fetch( {
					data: filters,
					success: function( collection, response, options ) {
						self.trigger( 'change' );
					}
				} );
			}
		} );

		/** Views ****************************************************/

		/**
		 * The filter bar
		 */
		BpbdFilterBar = Backbone.View.extend({
			members: null, /* the collection of backbonePeople */
			self:     this,
			el:       '#bpbd-filters',
			template: wp.template( 'bpbd_filters' ),
			search:   '',

			initialize: function( options ) {
				var self = this,
					router = options.router;
				this.members = options.members;
				this.render();

				// Text inputs should fire after a delay
				this.$el.on( 'keypress', 'input[type="text"]',_.debounce( function(){
					self.do_filter();
				}, 1000 ) );

				// Other inputs change immediately
				this.$el.on( 'change', 'input[type="checkbox"], input[type="radio"], select', function() {
					self.do_filter();
				} );
			},

			events: {
				'keypress #backbone_person-search-field':  'searchChange'
			},

			do_filter: function() {
				var self = this,
					all_values = {},
					field_name,
					field_name_clean,
					field_value,
					$this_field;

				self.$el.find( 'input, select' ).each( function( k, v ) {
					$this_field = $( v );

					field_name = $this_field.attr( 'name' );

					// Checkboxes
					if ( '[]' === field_name.substr( -2 ) ) {
						if ( $this_field.is( ':checked' ) ) {
							field_name_clean = field_name.slice( 0, -2 );

							if ( typeof all_values[ field_name_clean ] == 'undefined' ) {
								all_values[ field_name_clean ] = [];
							}

							all_values[ field_name_clean ].push( $this_field.val() );
						}

					// Skip the submit button
					} else if ( field_name != 'bpbd-submit' ) {
						// TODO - support multiple text input
						field_value = $this_field.val();

						if ( '' != field_value ) {
							all_values[ field_name ] = field_value;
						}
					}
				} );

				this.members.searchFor( all_values );
			},

			render: function() {
				var self = this;
				var templatehtml = this.template( this.search );
				this.$el.html( templatehtml );
				return this;
			}
		}),

		/**
		 * The Grid view - displays the directory grid
		 */
		BpbdDisplayList = Backbone.View.extend({
			members: null,
			el: '#members-list',
			template: wp.template( 'bpbd_member' ),

			initialize: function( options ) {
				this.members = options.members;
				this.router = options.router;
				this.listenTo( this.members, 'change', this.searchChanged );
			},

			searchChanged: function() {
				_.debounce( this.adjustSearch(), 250 );
			},

			adjustSearch: function() {

				// $.param builds the querystring from the filter object
				var navigate_to = '';
				if ( ! $.isEmptyObject( this.members.filters ) ) {
					navigate_to = '?' + $.param( this.members.filters );
				}
				this.router.navigate( navigate_to, { replace: false } );
				this.render();
			},

			render: function() {
				var self = this,
					member_models,
					search = '';
//					search = this.members.search.toLowerCase();

				member_models = this.members.models;

				var newEl = '';
				self.$el.html( '' );

				_.each( member_models, function( member ){
					//add the member to the list
					newEl += self.template( member.attributes );
				} );
				self.$el.html( newEl );

				self.$el.parent().find( '#backbone-person-count>span' ).html( member_models.length );

			}
		}),

		/**
		 * Router
		 */
		BpbdRouter = Backbone.Router.extend({
			routes: {
				'?bpbd-do-filter&*queryString': 'performSearch'
			},

			baseURL: function() {
				return window.location.pathname;
			},

			performSearch: function( s ) {
				BpbdApp.filter_bar.do_filter();
			}
		}),

		/**
		 * The main application object
		 */
		BpbdApp = {
			initialize: function() {
				var self = this, members, imgsrc, gravhash, fetched, fetched2,
				$loadcount = $( '#backbone_directory_loading_count' );
				self.members = new BpbdMemberCollection();
				self.members.url = BPBD.api_url;
				fetched = self.members.fetch();
				fetched.done( function( results ) {
					self.bpbd_router = new BpbdRouter();
					var options = {
						'members': self.members,
						'router': self.bpbd_router
					};
					self.display_list = new BpbdDisplayList( options );

					self.filter_bar = new BpbdFilterBar( options );

					self.bpbd_router.performSearch();

					self.display_list.render();
				});

				$( window ).on( 'resize', _.debounce( function() {
					var wide = $( this ).innerWidth() - 10 ;
					$( '#backbone_grid-container' ).height( '100%' );
					$( '#backbone_grid-container' ).width( wide + 'px' );
				}, 150 ) );

				Backbone.history.start( {
					pushState: true,
					root: window.location.pathname
				} );

				$( '#bpbd-submit' ).on( 'click', function() {
					BpbdApp.process_submit();
					return false;
				} );
			},

			process_submit: function() {
				console.log( 'ok' );
			}
		};

		BpbdApp.initialize();

	} );
} )( jQuery );
