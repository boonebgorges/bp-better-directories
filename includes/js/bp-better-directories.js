window.wp = window.wp || {};

( function( $ ) {
	$( document ).ready( function() {

		/**
		 * Member result
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
			search: '',

			setup: function( args ){
				this.columnNames = args;
			},

			searchFor: function( search ) {
				var self = this;
				this.fetch( {
					data: search,
					reset: true,
					success: function( collection, response, options ) {
						self.trigger( 'change' );
//						console.log( collection );
//						console.log( response );
//						console.log( options );
					}
				} );
//				this.search = search;
			}
		} );

		/** Views ****************************************************/

		/**
		 * The person display view - display the card of a single person
		 */
		BpbdMemberDisplay = Backbone.View.extend({

			template:  wp.template( 'backbone_person' ),
			el:        '#backbone_card',

			initialize: function() {
				this.listenTo( this.model, 'change', this.render );
			},

			render: function() {
				this.$el.html( this.template( this.model.attributes ) );
				return this;
			}
		}),

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
					} else {
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
			members: null, /* the collection of BpbdMember */
			el:       '#members-list',
			msnry:    null,
			template:  wp.template( 'bpbd_member' ),

			events: {
				'click .backbone_person-card': 'clickackbonePerson'
			},

			clickackbonePerson: function( person ) {
				console.log( 'triggering personDetail' );
				this.trigger( 'detailView', person );
			},

			initialize: function( options ) {
				console.log( 'BpbdDisplayList.initialize' );
				this.members = options.members;
				this.router = options.router;
				this.listenTo( this.members, 'change', this.searchChanged );
			},

			searchChanged: function() {
				_.debounce( this.adjustSearch(), 250 );
			},

			adjustSearch: function() {

				// If the search string is blank, don't include '?search=' string in navigation
				//var navigateto = ( '' === this.members.search ) ? '' : '?search=' + this.members.search;
//				this.router.navigate( navigateto, { replace: false } );
				console.log( 'BpbdDisplayList:rerendering after change'  );
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
				'?details=:name': 'openbackbonePerson',
				'?search=:search': 'performSearch'
			},

			baseURL: function() {
				return window.location.pathname;
			},

			openbackbonePerson: function( name ) {
				console.log( 'route: ' + name + ' ' );
				// find the model
				BackboneDirectoryApp.personDetail.model = BackboneDirectoryApp.backbonePeople.where({ 'name': name })[0];
				BackboneDirectoryApp.personDetail.render();
			},

			performSearch: function( s ) {
				console.log( 'Router::performSearch' );
				$( 'input#backbone_person-search-field' ).val( s ).trigger( 'keypress' ).select();
				BackboneDirectoryApp.personDetail.hidePersonDetailDialog();
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
					console.log( results );
					self.bpbd_router = new BpbdRouter();
					self.bpbd_member_display = new BpbdMemberDisplay( {
						model: new BpbdMember()
					});
					var options = {
						'members': self.members,
						'router': self.bpbd_router
					};
					self.display_list = new BpbdDisplayList( options );

					var filter_bar = new BpbdFilterBar( options );

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
			}
		};

		BpbdApp.initialize();

	} );
} )( jQuery );
