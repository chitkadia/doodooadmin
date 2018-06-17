<?php



ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
Individual table template


array('<table_name>'	array(
 								'tableDescription' 	=>	'Breif description about the table',
 								'utility'			=>	'Description and significance of the table in the system',
 								'columnDefination'	=>	array(
 																array(
 																 		'name'				=>	'<column_name>'
 																		'description' 		=> 	'description',
 																		'arrMapping' 		=>	array(
 																								'parentTable'	=>	'',
 																								'key'			=>	'',
 																								'description'	=>	''
 																								)
 																 	)
 								 							)
 							)
 	)
*/



$arrMaster = array(	
					'app_constant_vars'	=>	array(
													'tableDescription' 	=> 	'This table stores application constants values',
													'utility' 			=>	'Stores the Constants value and used to create app_vars.json file. ',
													'columnDefination'	=>	array(
																					array(
																							'name'			=>	'id',
																							'description'	=>	'Primary Key'
																					 	),
																					array(
																							'name'			=>	'code',
																							'description'	=>	'Unique Code to identify this constant'
																					 	),
																					array(
																							'name'			=>	'name',
																							'description'	=>	'Name of the constant'
																					 	),
																					array(
																							'name'			=>	'val',
																							'description'	=>	'Value of the constant'
																					 	),
																					array(
																							'name'			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name'			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
													 							)
					 							),
					'api_messages'		=>	array(
													'tableDescription' 	=>	'Stores all the front end api / webhooks response messages.',
													'utility' 			=>	'Stores all the api messages those are used in the front end / webhooks',
													'columnDefination' 	=>array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Api Message Id, Primary key'
																					 	),
																					array(
																							'name' 			=>	'code',
																							'description' 	=>	'Unique Identifier for every api message'
																					 	),
																					array(
																							'name' 			=>	'http_code',
																							'description' 	=>	'Http status code that returns with the api response.'
																					 	),
																					array(
																							'name' 			=>	'error_code',
																							'description' 	=>	'this code is returned to the front end in api response.'
																					 	),
																					array(
																							'name' 			=>	'error_message',
																							'description' 	=>	'Response message of the API'
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'0-Inactive, 1-Active'
																					 	),
																					array(
																							'name' 			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	)
													 							)
					 							),

	 				'source_master'		=>	array(
	 												'tableDescription' 	=>	'List of sources from where the users can access the system',
													'utility'			=>	'List of sources from where the users can access the system',
													'columnDefination'	=> array(
																					array(
																							'name'			=>	'id',
																							'description'	=>	'Source master id, Primary Key',
																					 	),
																					array(
																							'name'			=>	'code',
																							'description'	=>	'Unique code of the source',
																					 	),
																					array(
																							'name'			=>	'name',
																							'description'	=>	'Name of the source',
																					 	),
																					array(
																							'name'			=>	'status',
																							'description'	=>	'0-Inactive, 1-Active',
																					 	),
																					array(
																							'name'			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name'			=>	'modified',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																				)
	 											),
	 				'document_source_master'	=>	array(
	 														'tableDescription' 	=>	'',
	 														'utility' 			=>	'',
	 														'columnDefination' 	=>	array(
	 																						array(
	 																								'name'			=>	'id',
	 																								'description' 	=>	'Document Source Master id, primary key'
	 																						 	),
	 																						array(
	 																								'name'			=>	'code',
	 																								'description' 	=>	'Unique Identifier for the doscument source,'
	 																						 	),
	 																						array(
	 																								'name'			=>	'name',
	 																								'description' 	=>	'Name of the source from where documents can be uploaded'
	 																						 	),
	 																						array(
	 																								'name'			=>	'status',
	 																								'description' 	=>	'0-Inactive, 1-Active'
	 																						 	),
	 																						array(
	 																								'name'			=>	'created',
	 																								'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
	 																						 	),
	 																						array(
	 																								'name'			=>	'modified',
	 																								'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
	 																						 	),

	 														 							)
	 				 									),

	 				'social_login_master' 	=>	array(
	 													'tableDescription' 	=> 	'Table stores all the type of social login\'s that will be supported by the system',
	 													'utility' 			=> 	'Table stores all the type of social login\'s that will be supported by the system',
	 													'columnDefination' 	=> array(
	 														 							array(
	 														 								 	'name' 			=>	'id',
	 														 								 	'description' 	=> 	'Social Login Master Id, Primary Key'
																							),
	 														 							array(
	 														 								 	'name' 			=>	'code',
	 														 								 	'description' 	=> 	'Unique Code to identify the types of social login.'
																							),
	 														 							array(
	 														 								 	'name' 			=>	'name',
	 														 								 	'description' 	=> 	'Name of the social media website'
																							),
	 														 							array(
	 														 								 	'name' 			=>	'status',
	 														 								 	'description' 	=> 	'0-Inactive, 1-Active'
																							),
	 														 							array(
	 														 								 	'name' 			=>	'created',
	 														 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																							),
	 														 							array(
	 														 								 	'name' 			=>	'modified',
	 														 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																							),
	 													 							)
	 												),

	 				'resource_master'	=>	array(
													'tableDescription' 	=>	'Stores list of all the resources in the system',
													'utility'			=>	'Content of this table is used to assign resources to the roles. These are used as a template when first time roles are getting created.',
													'columnDefination'	=> array(
																					array(
																							'name'			=>	'id',
																							'description'	=>	'Resource master id, Primary Key',
																					 	),
																					array(
																							'name'			=>	'resource_name',
																							'description'	=>	'Name of the resource',
																					 	),
																					array(
																							'name'			=>	'short_info',
																							'description'	=>	'Description of the resource, Why this resource is created.',
																					 	),
																					array(
																							'name'			=>	'api_endpoint',
																							'description'	=>	'List of URL\'s that are used for this resource. API resource name',
																					 	),
																					array(
																							'name'			=>	'parent_id',
																							'description'	=>	'Resources can child of any other resource, Here parimary key of other resource_master record. Like for Email resource it\'s add / update and delete will be child resource',
																							'arrMapping'	=>	array(
																														'parentTable' 	=> 	'resource_master',
																														'key'			=>	'id',
																														'description'	=>	'This key is from same table.'
																							 						)
																					 	),
																					array(
																							'name'			=>	'position',
																							'description'	=>	'Ordering of the child resources.',
																					 	),
																					array(
																							'name'			=>	'show_in_roles',
																							'description'	=>	'Whether this resource is available for Roles OR not. 0-No, 1-Yes',
																					 	),
																					array(
																							'name'			=>	'show_in_webhooks',
																							'description'	=>	'Whether this resource is available for webhooks. 0-No, 1-Yes',
																					 	),
																					array(
																							'name'			=>	'is_always_assigned',
																							'description'	=>	'0-No, 1-Yes (Whether this resource is default assigned or not)',
																					 	),
																					array(
																							'name'			=>	'is_secured',
																							'description'	=>	'Whether this resource needs authentication of user OR not',
																					 	),
																					array(
																							'name'			=>	'status',
																							'description'	=>	'0-Inactive, 1-Active',
																					 	),
																					array(
														 									'name'		=>	'created',
														 									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
														 								),
														 							array(
														 									'name'		=>	'modified',
														 									'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
														 								)
													 							)
												),
	 				'role_master'		=>	array(
	 												'tableDescription' 	=>	'List of role master',
													'utility'			=>	'This table is used to manage all the roles defined by the user',
													'columnDefination'	=> array(
																					array(
																							'name'			=>	'id',
																							'description'	=>	'Role master id, Primary Key',
																					 	),
																					array(
																							'name'			=>	'account_id',
																							'description'	=>	'If any site user has created the role, then it\'s account id AND If it is a sysmtem based role then it\'s value will be NULL',
																							'arrMapping'	=>	array(
																														'parentTable' 	=>	'account_master',
																														'key'			=>	'id',
																														'description'	=>	'Foreign Key'
																							 						)
																					 	),
																					array(
																							'name'			=>	'user_id',
																							'description'	=>	'If any site user has created the role, then it\'s user\'s tables id AND If it is a sysmtem based role then it\'s value will be NULL',
																							'arrMapping'	=>	array(
																														'parentTable' 	=>	'user_master',
																														'key'			=>	'id',
																														'description'	=>	'Foreign Key'
																							 						)
																					 	),
																					array(
																							'name'			=>	'source_id',
																							'description'	=>	'If any site user has created the role, then from which source AND If it is a sysmtem based role then it\'s value will be NULL',
																							'arrMapping'	=>	array(
																														'parentTable' 	=>	'source_master',
																														'key'			=>	'id',
																														'description'	=>	'Foreign Key'
																							 						)
																					 	),
																					array(
																							'name'			=>	'code',
																							'description'	=>	'Unique Identifier of the role',
																					 	),
																					array(
																							'name'			=>	'name',
																							'description'	=>	'Name of the role',
																					 	),
																					array(
																							'name'			=>	'short_info',
																							'description'	=>	'Short description of the role.',
																					 	),
																					array(
																							'name'			=>	'is_system',
																							'description'	=>	'Whether it is system based role OR User defined role. 0-No, 1-Yes',
																					 	),
																					array(
																							'name'			=>	'for_customers',
																							'description'	=>	'Whether this role is for customer OR not. 0-No, 1-Yes',
																					 	),
																					array(
																							'name'			=>	'status',
																							'description'	=>	'0-Inactive, 1-Active',
																					 	),
																					array(
																							'name'			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name'			=>	'modified',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																				)
	 											),
	 				'role_default_resources' 	=>	array(
	 														'tableDescription' 	=>	'Default Resources to the roles.',
															'utility'			=>	'This table contains default resources to the roles. When ever a user is added with new role, these resources will get automatically assigned to that user. This table is only used as template for default resources for the role.',
															'columnDefination'	=> array(
																							array(
																									'name'			=>	'id',
																									'description'	=>	'Source master id, Primary Key',
																							 	),
																							array(
																									'name'			=>	'role_id',
																									'description'	=>	'Id of the role to which need to allocate the resource',
																									'arrMapping'	=>	array(
																																'parentTable' 	=>	'role_master',
																																'key'			=>	'id',
																																'description'	=>	'&nbsp;'
																									 						)
																								),
																							array(
																									'name'			=>	'resource_id',
																									'description'	=>	'Id of the resource that need\'s to be default assign to the role.',
																									'arrMapping'	=>	array(
																																'parentTable' 	=>	'resource_master',
																																'key'			=>	'id',
																																'description'	=>	'&nbsp;'
																									 						)
																								),
																							array(
																									'name'			=>	'status',
																									'description'	=>	'1-Active, 2-Deleted',
																							 	),
																							array(
																									'name'			=>	'modified',
																									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																							 	)
																						)
	 				 									),
	 				
					
	 				'account_master' => array(
												'tableDescription' 	=>	'Master table for storing all the accounts related information',
												'utility'			=>	'This table is created for the individual account for any signup from new email which is not present in our system',
												'columnDefination'	=>	array(
													 							array(
													 								 	'name'			=>	'id',
													 									'description'	=>	'Account master id, Primary key'
													 								),
													 							array(
													 									'name'			=>	'ac_number',
													 									'description'	=>	'Account number of the account. This field will be customer readable.'
													 								),
													 							array(
													 									'name'			=>	'source_id',
													 									'description'	=>	'Source from where account has been created, ( Default "WEB_APP", from "source_master" )',
													 									'arrMapping'	=>	array(
													 																'parentTable'	=>	'source_master',
													 																'key'			=>	'id',
													 																'description'	=>	'Foreign Key'
													 															)
													 								),
													 							array(
													 									'name'			=>	'configuration',
													 									'description'	=>	'Account related configuration will be stored in JSON format'
													 								),
													 							array(
													 									'name'		=>	'status',
													 									'description'	=>	'Status of the account, 0-Inactive, 1-Active, 2-Deleted'
													 								),
													 							array(
													 									'name'		=>	'created',
													 									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
													 								),
													 							array(
													 									'name'		=>	'modified',
													 									'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
													 								)
																			)
											),
					'account_organization'	=>	array(
												'tableDescription' 	=>	'Stores the origanization detail of the account',
												'utility'			=>	'This table have all the organization(Branding) related information for an account,',
												'columnDefination'	=>	array(
																				array(
																						'name'		=>	'id',
																						'description' 	=>	'Account organization id, Primary key'
																				 	),
																				array(
																						'name'			=>	'account_id',
																						'description' 	=>	'Primary key of the account_master table',
																						'arrMapping'	=>	array(
																							 						'parentTable'	=>	'account_master',
																							 						'key'			=>	'id',
																													'description'	=>	'This will be ono-to-one relation.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'name',
																						'description' 	=>	'Name of the organization',
																				 	),
																				array(
																						'name'			=>	'address',
																						'description' 	=>	'Address of the organization',
																				 	),
																				array(
																						'name'			=>	'city',
																						'description' 	=>	'city of the organization',
																				 	),
																				array(
																						'name'			=>	'state',
																						'description' 	=>	'State of the organization',
																				 	),
																				array(
																						'name'			=>	'country',
																						'description' 	=>	'Country of the organization',
																				 	),
																				array(
																						'name'			=>	'zipcode',
																						'description'	=>	'Zipcode of the organization',
																				 	),
																				array(
																						'name'			=>	'logo',
																						'description' 	=>	'File path of the logo of organization',
																				 	),
																				array(
																						'name'			=>	'website',
																						'description' 	=>	'Website of the organization',
																				 	),
																				array(
																						'name'			=>	'contact_phone',
																						'description' 	=>	'Phone number of the organization',
																				 	),
																				array(
																						'name'			=>	'contact_fax',
																						'description' 	=>	'Fax number of the origanization',
																				 	),
																				array(
																						'name'			=>	'short_info',
																						'description' 	=>	'Description about the organization',
																				 	),
																				array(
																						'name'			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)

																			)
					 							),
					'user_master'	=>array(
												'tableDescription' 	=>	'This table stored information about all the users in the system. ',
												'utility' 			=>	'This table is the first table in hierarchy when come to store information about the user',
												'columnDefination'	=>	array(
																				array(
																						'name'			=>	'id',
																						'description'	=>	'User master table\'s Primary key'
																				 	),
																				array(
																						'name'			=>	'account_id',
																						'description'	=>	'Connected Account Id',
																						'arrMapping'	=>	array(
																													'parentTable' 	=>	'account_master',
																													'key'			=>	'id',
																													'description'	=>	'Connected Account Id.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'user_type_id',
																						'description'	=>	'User Type Id, Not Used now, But Will use in when creating Admin panel of the saleshandy V2.',
																						'arrMapping'	=>	array(
																													'parentTable' 	=>	'user_type_master',
																													'key'			=>	'id',
																													'description'	=>	'User Type Id.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'role_id',
																						'description'	=>	'Attached Role Id',
																						'arrMapping'	=>	array(
																													'parentTable' 	=>	'role_master',
																													'key'			=>	'id',
																													'description'	=>	'Associated Role Id.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'source_id',
																						'description'	=>	'From which source the user has signed up.',
																						'arrMapping'	=>	array(
																													'parentTable' 	=>	'source_master',
																													'key'			=>	'id',
																													'description'	=>	'Source Master Id.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'first_name',
																						'description'	=>	'First Name of the user'
																				 	),
																				array(
																						'name'			=>	'last_name',
																						'description'	=>	'Last Name of the user'
																				 	),
																				array(
																						'name'			=>	'email',
																						'description'	=>	'Email of the user'
																				 	),
																				array(
																						'name'			=>	'password',
																						'description'	=>	'Encrypted Password for the user'
																				 	),
																				array(
																						'name'			=>	'password_salt_key',
																						'description'	=>	'Salt by which the users password is encrypted'
																				 	),
																				array(
																						'name'			=>	'photo',
																						'description'	=>	'File path of the user\'s profile photo'
																				 	),
																				array(
																						'name'			=>	'phone',
																						'description'	=>	'Phone number of the user'
																				 	),
																				array(
																						'name'			=>	'last_login',
																						'description'	=>	'Last login time of the user, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name'			=>	'verified',
																						'description'	=>	'Whether the user is verified by the system OR not, 0-No, 1-Yes'
																				 	),
																				array(
																						'name'			=>	'status',
																						'description'	=>	'Staus of the user, 0-Inactive, 1-Active, 2-Deleted, 5-Removed'
																				 	),
																				array(
																						'name'			=>	'created',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name'			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
											),
				'user_resources' 	=>	array(
												'tableDescription' 	=>	'List of resource those are accessible by the user.',
												'utility' 			=>	'The purpose of this table is to store all the resource mapping with the table that are assigned at the time of Member creation. This table store the temalpted value of associated resource with the user. These can be changed by updating member information from the Member screen',
												'columnDefination'	=>	array(
																				array(
																						'name'			=>	'id',
																						'description'	=>	'Primary Key'
																				 	),
																				array(
																						'name'			=>	'user_id',
																						'description'	=>	'User Id of user',
																						'arrMapping'	=>	array(
																													'parentTable' 	=>	'user_master',
																													'key'			=>	'id',
																													'description'	=>	'user id'
																						 						)
																				 	),
																				array(
																						'name'			=>	'resource_id',
																						'description'	=>	'Resource Id of the resource.',
																						'arrMapping'	=>	array(
																													'parentTable'	=>	'resource_master',
																													'key' 			=>	'id',
																													'description'	=>	'Id of the resource master table.'
																						 						)
																				 	),
																				array(
																						'name'			=>	'status',
																						'description'	=>	'1-Active, 2-Deleted'
																					),
																				array(
																						'name'			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
																			)
											),
				'user_settings'		=>	array(
												'tableDescription' 	=>	'Stores users\'s preferences',
												'utility'			=>	'This table manages the relation between app_constant_vars and user_settings. From app_constant_var it gets the user settiongs preference name, and store it\'s value.',
												'columnDefination'	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Primary Key'
																				 	),
																				array(
																						'name' 			=>	'user_id',
																						'description' 	=>	'For which user is this setting for',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'user_master',
																													'key'			=>	'id',
																													'description'	=>	'Connected User Id'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'app_constant_var_id',
																						'description' 	=>	'Connected app_constant_vars tables row.',
																						'arrMapping'	=>	array(
																													'parentTable' 	=> 	'app_constant_vars',
																													'key'			=>	'id',
																													'description' 	=> 	'Connected app_constant_vars Id'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'value',
																						'description' 	=>	'Value of the app_constant_vars constant'
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),

												 							)
											),
				'user_type_master' 	=> 	array(
												'tableDescription' 	=>	'User type master table',
												'utility' 			=> 	'This table stores the different user types for the admin panel. This table will come in action when working on admin panel of the saleshandy.',
												'columnDefination' 	=>	array(
																				array(
																						'name' 		 	=>	'id',
																						'description'	=>	'Primary key of the table'
																				 	),
																				array(
																						'name' 		 	=>	'code',
																						'description'	=>	'Unique code to identify the user type.'
																				 	),
																				array(
																						'name' 		 	=>	'name',
																						'description'	=>	'Name of the user type'
																				 	),
																				array(
																						'name' 		 	=>	'status',
																						'description'	=>	'0-Inactive, 1-Active'
																				 	),
																				array(
																						'name' 		 	=>	'created',
																						'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 		 	=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'user_authentication_tokens'	=> array(
															'tableDescription' 	=>	'Used for storing users authorization token.',
															'utility'			=>	'This table stores users authorization information. It generated auth token at the time of login and that token is used to access API\'s.',
															'columnDefination'	=>	array(
																							array(
																								 	'name' 			=>	'id',
																								 	'description' 	=> 	'User Aithentication token Id, Primary Key',
																							 	),
																							array(
																								 	'name' 			=>	'user_id',
																								 	'description' 	=> 	'Stores user id of the user for which auth token is getting generated.',
																								 	'arrMapping' 	=>	array(
																								 								'parentTable' 	=> 	'user_master',
																								 								'key'			=>	'id',
																								 								'description' 	=> 	'&nbsp;'
																								 	 						)
																							 	),
																							array(
																								 	'name' 			=>	'source_id',
																								 	'description' 	=> 	'Stores the value of the origin of the request',
																								 	'arrMapping' 	=>	array(
																								 								'parentTable' 	=> 	'source_master',
																								 								'key' 			=> 	'id',
																								 								'description' 	=> 	'&nbsp;'
																								 	 						)
																							 	),
																							array(
																								 	'name' 			=>	'token',
																								 	'description' 	=> 	'Encrypted string, which is the hash of user_id + source_id + generated_at. This token is used send from the front end as "X-Authorization-Token" header.',
																							 	),
																							array(
																								 	'name' 			=>	'generated_at',
																								 	'description' 	=> 	'When this token is generated, In UNIX Timestamp, TimeZone (+00:00)',
																							 	),
																							array(
																								 	'name' 			=>	'expires_at',
																								 	'description' 	=> 	'When the token is getting expire, In UNIX Timestamp, TimeZone (+00:00)',
																							 	),
																							array(
																								 	'name' 			=>	'user_resources',
																								 	'description' 	=> 	'String of user\'s accessible resources will be stored here in JSON format.',
																							 	),
															 							)
				 										),
				'user_social_login'		=>	array(
													'tableDescription' 	=>	'This table used to store users signup\'s by the social media.',
													'utility' 			=> 	'This table used to store users signup\'s by the social media.',
													'columnDefination' 	=> 	array(
														 							array(
														 								 	'name' 			=>	'id',
														 								 	'description' 	=> 	'Users Social Login Id, Primary Key'
														 							 	),
														 							array(
														 								 	'name' 			=>	'user_id',
														 								 	'description' 	=> 	'User id of the user who has signuped via social account.',
														 								 	'arrMapping' 	=> 	array(
														 								 		 						'parentTable' 	=> 	'user_master',
														 								 		 						'key' 			=> 	'id',
														 								 		 						'description' 	=> 	'User Id'
														 								 	 						)
														 							 	),
														 							array(
														 								 	'name' 			=>	'social_login_id',
														 								 	'description' 	=> 	'Social login master tables primary key, to identify from which social media user has signedup.',
														 								 	'arrMapping' 	=> 	array(
														 								 		 						'parentTable' 	=> 	'social_login_master',
														 								 		 						'key' 		 	=> 	'id',
														 								 		 						'description' 	=> 	''
														 								 	 						)
														 							 	),
														 							array(
														 								 	'name' 			=>	'signin_info',
														 								 	'description' 	=> 	'String of user\'s social signup realted all information in JSON format'
														 							 	),
														 							array(
														 								 	'name' 			=>	'status',
														 								 	'description' 	=> 	'1-Active, 2-Deleted'
														 							 	),
														 							array(
														 								 	'name' 			=>	'modified',
														 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
														 							 	),
													 							)
				 								),

				'user_members'	=>	array(
											'tableDescription' 	=> 	'Mapping table for the Team owner and team members',
											'utility' 			=>	'This is a mapping table between team owner and team members. Team owners id will be associated with the team memebers id. Team owner will have one-to-many relation with the team member.',
											'columnDefination' 	=> 	array(
																			array(
																					'name'			=>	'id',
																					'description' 	=> 	'Primary Key'
																			 	),
																			array(
																					'name'			=>	'user_id',
																					'description' 	=> 	'This is the team owner\'s Id.',
																					'arrMapping' 	=> 	array(
																												'parentTable' 	=> 	'user_master',
																												'key' 			=> 	'id',
																												'description' 	=> 	'Team Owner Id,'
																					 						)
																			 	),
																			array(
																					'name'			=>	'has_access_of',
																					'description' 	=> 	'THis is the team member\'s Id.',
																					'arrMapping' 	=> 	array(
																												'parentTable' 	=> 	'user_master',
																												'key' 			=>	'id',
																												'description' 	=> 	'Team member Id'
																					 						)
																			 	),
											 							)
				 						),
				'user_invitations' 	=>	array(
												'tableDescription' 	=> 	'Users Invitaion table',
												'utility' 			=> 	'This table stores the invitation information that the team owner has sent to any receipent.',
												'columnDefination' 	=> 	array(
													 							array(
													 								 	'name' 			=>	'id',
													 								 	'description'	=> 	'Primary Key',
													 							 	),
													 							array(
													 								 	'name' 			=>	'account_id',
													 								 	'description'	=> 	'Team owners account id',
													 								 	'arrMapping' 	=> 	array(
													 								 								'parentTable' 	=> 	'account_master',
													 								 								'key'			=>	'id',
													 								 								'description' 	=> 	'Account Master table\'s primary key'
													 								 							)
													 							 	),
													 							array(
													 								 	'name' 			=>	'user_id',
													 								 	'description'	=> 	'New member will be created under Teams account. and It\'s id will be set over here.',
													 								 	'arrMapping' 	=>	array(
													 								 								'parentTable' 	=> 	'user_master',
													 								 								'key'			=>	'id',
													 								 								'description' 	=>	'Team members Id, who is sending the invitation'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'invited_by',
													 								 	'description'	=> 	'The Id of the Team member who is sending invitation will be set over here',
													 								 	'arrMapping' 	=>	array(
													 								 								'parentTable' 	=> 	'user_master',
													 								 								'key'			=>	'id',
													 								 								'description' 	=>	'Team members Id, who is sending the invitation'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'invited_at',
													 								 	'description'	=> 	'Invitaion time, In UNIX Timestamp, TimeZone (+00:00)',
													 							 	),
													 							array(
													 								 	'name' 			=>	'joined_at',
													 								 	'description'	=> 	'User accepting the invitaion time, In UNIX Timestamp, TimeZone (+00:00)',
													 							 	),
													 							array(
													 								 	'name' 			=>	'status',
													 								 	'description'	=> 	'1-Active, 2-Deleted',
													 							 	),
													 							array(
													 								 	'name' 			=>	'created',
													 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
													 							 	),
													 							array(
													 								 	'name' 			=>	'modified',
													 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
													 							 	),
												 							)
				 							),
				'user_actions' 		=> 	array(
					 							'tableDescription' 	=> 	'This table is used to records all the action\'s taken by the user',
					 							'utility' 			=>	'This table will store all the actions taken by the user',
					 							'columnDefination' 	=> 	array(
					 								 							array(
					 								 								 	'name' 			=> 	'id',
					 								 								 	'description' 	=> 	'User\'s action Id, Primary Key'
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'account_id',
					 								 								 	'description' 	=> 	'Account id of the user who is performing any action.',
					 								 								 	'arrMapping' 	=> 	array(
					 								 								 		 						'parentTable' 	=> 	'account_master',
					 								 								 		 						'key'			=> 	'id',
					 								 								 		 						'description' 	=> 	'Account master Id'
					 								 								 	 						)
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'user_id',
					 								 								 	'description' 	=> 	'User id of the user whoc is performing any action',
					 								 								 	'arrMapping' 	=> 	array(
					 								 								 		 						'parentTable' 	=> 	'user_master',
					 								 								 		 						'key' 			=> 	'id',
					 								 								 		 						'description' 	=> 	'User master table id'
					 								 								 	 						)
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'source_id',
					 								 								 	'description' 	=> 	'Source from where user id performing the action.',
					 								 								 	'arrMapping' 	=> 	array(
					 								 								 		 						'parentTable' 	=> 	'source_master',
					 								 								 		 						'key' 			=> 	'id',
					 								 								 		 						'description' 	=> 	'Source master Id'
					 								 								 	 						)
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'resource_id',
					 								 								 	'description' 	=> 	'What resurce the user is accessing in this action',
					 								 								 	'arrMapping' 	=> 	array(
					 								 								 		 						'parentTable' 	=> 	'resource_master',
					 								 								 		 						'key' 			=>	'id',
					 								 								 		 						'description' 	=> 	'Resource master Id'
					 								 								 	 						)
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'model',
					 								 								 	'description' 	=> 	'Which model user trying to access in this action'
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'record_id',
					 								 								 	'description' 	=> 	'????????????????'
					 								 							 	),
					 								 							array(
					 								 								 	'name' 			=> 	'created',
					 								 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
					 								 							 	),
					 							 							)
				 							),
				'user_pass_reset_requests'	=>	array(
					 									'tableDescription' 	=> 	'This table is used to store the reset password link for individual user.',
					 									'utility' 			=> 	'This table saves all the requests by the users for passowrd reset. It stores the token for password request which mailed to the user.',
					 									'columnDefination' 	=> 	array(
					 										 							array(
					 										 								 	'name' 			=>	'id',
					 										 								 	'description' 	=> 	'User password reset request Id, Primary Key'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'user_id',
					 										 								 	'description' 	=> 	'User Id of the user who is requesting for password reset.',
					 										 								 	'arrMapping' 	=> 	array(
					 										 								 		 						'parentTable' 	=> 	'user_master',
					 										 								 		 						'key' 			=> 	'id',
					 										 								 		 						'description' 	=> 	'User\'s Table, Primary Key'
					 										 								 	 						)
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'request_token',
					 										 								 	'description' 	=> 	'This is the request token which is going to be in URL for rest password. This column stores the hashid of user_id + request_date + expires_at'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'request_date',
					 										 								 	'description' 	=> 	'Date when users request for the password reset, In UNIX Timestamp, TimeZone (+00:00)'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'expires_at',
					 										 								 	'description' 	=> 	'When the request token will get\'s expire'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'password_reset',
					 										 								 	'description' 	=> 	'Flag, wether the passowrd has been reset with this rest token, 0-No, 1-Yes '
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'modified',
					 										 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
					 										 							 	)
					 									 							)
				 									),

				'account_teams' 	=>	array(
												'tableDescription' 	=> 	'Stores the information of the account teams.',
												'utility' 			=> 	'This table stores information about the teams created within an account.',
												'columnDefination' 	=>	array(
													 							array(
													 								 	'name' 			=>	'id',
													 								 	'description' 	=> 	'Account Team Id, Primary Key'
													 							 	),
													 							array(
													 								 	'name' 			=>	'account_id',
													 								 	'description' 	=> 	'Account master table Id.',
													 								 	'arrMapping' 	=>	array(
													 								 								'parentTable' 	=> 	'account_master',
													 								 								'key' 			=>	'id',
													 								 								'description' 	=> 	'A single account can have many teams, one-to-many relation.'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'source_id',
													 								 	'description' 	=> 	'From which source the team is created.',
													 								 	'arrMapping' 	=> 	array(
													 								 		 						'parentTable' 	=> 	'source_master',
													 								 		 						'key' 			=> 	'id',
													 								 		 						'description' 	=> 	'&nbsp;'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'name',
													 								 	'description' 	=> 	'Name of the Team'
													 							 	),
													 							array(
													 								 	'name' 			=>	'user_id',
													 								 	'description' 	=> 	'User Id of the user, who is creating the team',
													 								 	'arrMapping' 	=> 	array(
													 								 								'parentTable' 	=> 	'user_master',
													 								 								'key' 			=>	'id',
													 								 								'description' 	=> 	'&nbsp;'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'owner_id',
													 								 	'description' 	=> 	'Id of the user who is the owner of the account.',
													 								 	'arrMapping' 	=> 	array(
													 								 		 						'parentTable' 	=> 	'user_master',
													 								 		 						'key' 			=> 	'id',
													 								 		 						'description' 	=> 	'&nbsp;'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'manager_id',
													 								 	'description' 	=> 	'Id of the user who is managing the team.',
													 								 	'arrMapping' 	=> 	array(
													 								 		 						'parentTable' 	=> 	'user_master',
													 								 		 						'key' 			=> 	'id',
													 								 		 						'description' 	=> 	'&nbsp;'
													 								 	 						)
													 							 	),
													 							array(
													 								 	'name' 			=>	'status',
													 								 	'description' 	=> 	'1-Active, 2-Delete'
													 							 	),
													 							array(
																						'name'			=>	'created',
																						'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name'			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'account_team_members' 	=> 	array(
					 								'tableDescription' 	=> 	'Stores which members associated with a team.',
					 								'utility' 			=> 	'This is mapping table between the team and team members.',
					 								'columnDefination' 	=> 	array(
					 									 							array(
					 									 								 	'name' 			=> 	'id',
					 									 								 	'description' 	=> 	'Account Team Members Id, Primary Key'
					 									 							 	),
					 									 							array(
					 									 								 	'name' 			=> 	'account_team_id',
					 									 								 	'description' 	=> 	'Team is created for which account.',
					 									 								 	'arrMapping' 	=> 	array(
					 									 								 		 						'parentTable'	=> 	'account_master',
					 									 								 		 						'key' 			=> 	'id',
					 									 								 		 						'description' 	=> 	'&nbsp;'
					 									 								 	 						)
					 									 							 	),
					 									 							array(
					 									 								 	'name' 			=> 	'user_id',
					 									 								 	'description' 	=> 	'Id of the team members that are associated with the team',
					 									 								 	'arrMapping' 	=> 	array(
					 									 								 		 						'parentTable' 	=> 	'user_master',
					 									 								 		 						'key' 			=> 	'id',
					 									 								 		 						'description' 	=> 	'&nbsp;'
					 									 								 	 						)
					 									 							 	),
					 									 							array(
					 									 								 	'name' 			=> 	'status',
					 									 								 	'description' 	=> 	'1-Active, 2-Deleted'
					 									 							 	),
					 									 							array(
					 									 								 	'name' 			=> 	'modified',
					 									 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
					 									 							 	),

					 								 							)
				 								),

				'payment_method_master' 	=>	array(
					 									'tableDescription' 	=> 	'Stores payment options for the customers.',
					 									'utility' 			=> 	'Stores payment options for the customers. We will be using this table to manage customer paying options to saleshandy.',
					 									'columnDefination' 	=>	array(
					 										 							array(
					 										 								 	'name' 			=>	'id',
					 										 								 	'description' 	=> 	'Payment method master Id, Primary Key'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'code',
					 										 								 	'description' 	=> 	'Unique Identifier for addressing payment mehod.'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'name',
					 										 								 	'description' 	=> 	'Name of the payment method.'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'status',
					 										 								 	'description' 	=> 	'0-Inactive, 1-Active'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'created',
					 										 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
					 										 							 	),
					 										 							array(
					 										 								 	'name' 			=>	'modified',
					 										 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
					 										 							 	),
					 									 							)
				 									),
				'coupon_master'		=>	array(
												'tableDescription' 	=>	'Stores and Manages all the coupons that can be used by customers',
												'utility' 			=>	'Stores and Manages all the coupons that can be used by customers',
												'columnDefination' 	=> 	array(
																				array(
																					 	'name' 			=>	'id',
																					 	'description'	=>	'Coupon Master Id, primary key',
																				 	),
																				array(
																					 	'name' 			=>	'code',
																					 	'description'	=>	'Unique coupon code to identify coupon',
																				 	),
																				array(
																					 	'name' 			=>	'valid_from',
																					 	'description'	=>	'Coupon valid from datetime, In UNIX Timestamp, TimeZone (+00:00)',
																				 	),
																				array(
																					 	'name' 			=>	'valid_to',
																					 	'description'	=>	'Coupon valid till datetime, In UNIX Timestamp, TimeZone (+00:00)',
																				 	),
																				array(
																					 	'name' 			=>	'min_amount',
																					 	'description'	=>	'Minimum amount to apply the coupon',
																				 	),
																				array(
																					 	'name' 			=>	'max_amount',
																					 	'description'	=>	'Maximum acount to apply the coupon',
																				 	),
																				array(
																					 	'name' 			=>	'discount_type',
																					 	'description'	=>	'Discount Type, enum (AMT, PER). AMT - Fixed Amount discount:: PER - Percentage Discount',
																				 	),
																				array(
																					 	'name' 			=>	'discount_value',
																					 	'description'	=>	'Discout value in decimal 12,4',
																				 	),
																				array(
																					 	'name' 			=>	'currency',
																					 	'description'	=>	'3 Digit Currency Code, used for payment, Default we are using USD.',
																				 	),
																				array(
																					 	'name' 			=>	'short_info',
																					 	'description'	=>	'Short description about the coupon generated',
																				 	),
																				array(
																					 	'name' 			=>	'status',
																					 	'description'	=>	'0-Inactive, 1-Active, 2-Deleted',
																				 	),
																				array(
																					 	'name' 			=>	'created',
																					 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																					 	'name' 			=>	'modified',
																					 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
												 							)
				 							),
				'plan_master'	=>	array(
											'tableDescription' 	=> 	'Manages all the plans that sales handy provides to the customers',
											'utility' 			=>	'All the billing and subscriptions are based on these plans',
											'columnDefination' 	=> 	array(
												 							array(
												 								 	'name' 			=>	'id',
												 								 	'description' 	=> 	'Plan Master Id, Primary Key'
												 							 	),
												 							array(
												 								 	'name' 			=>	'code',
												 								 	'description' 	=> 	'Unique code to identify the plan master'
												 							 	),
												 							array(
												 								 	'name' 			=>	'name',
												 								 	'description' 	=> 	'Name of the plan, that will gets displayed in front end'
												 							 	),
												 							array(
												 								 	'name' 			=>	'amount',
												 								 	'description' 	=> 	'Amount of the plan'
												 							 	),
												 							array(
												 								 	'name' 			=>	'currency',
												 								 	'description' 	=> 	'3 Digit Currency Code, Default we are using USD'
												 							 	),
												 							array(
												 								 	'name' 			=>	'mode',
												 								 	'description' 	=> 	'0-Unlimited, 1-Monthly, 3-3 Months, 6-6 Months, 12-Yearly'
												 							 	),
												 							array(
												 								 	'name' 			=>	'validity_in_days',
												 								 	'description' 	=> 	'validity in number of days. 0-Unlimited Days'
												 							 	),
												 							array(
												 								 	'name' 			=>	'configuration',
												 								 	'description' 	=> 	'Store Plan related information in JSON format'
												 							 	),
												 							array(
												 								 	'name' 			=>	'short_info',
												 								 	'description' 	=> 	'Short description about the plan and it\'s features'
												 							 	),
												 							array(
												 								 	'name' 			=>	'description',
												 								 	'description' 	=> 	'Description about the plan.'
												 							 	),
												 							array(
												 								 	'name' 			=>	'status',
												 								 	'description' 	=> 	'0-Inactive, 1-Active'
												 							 	),
												 							array(
												 								 	'name' 			=>	'created',
												 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
												 							 	),
												 							array(
												 								 	'name' 			=>	'modified',
												 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
												 							 	)
											 							)
				 						),

				'account_subscription_details'	=>	array(
															'tableDescription' 	=> 	'Stores all the subscriptions subscribed by customers',
															'utility' 			=>	'Stores all the subscriptions subscribed by customers. When customer signup\'s then a default entry for the 14 days free enterprise as a subscription will be inserted in this table.',
															'columnDefination' 	=> 	array(
																 							array(
																 								 	'name' 			=> 	'id',
																 								 	'description' 	=> 	'Account Subscription Details Id, Primary Key'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'account_id',
																 								 	'description' 	=> 	'Customers Account Id',
																 								 	'arrMapping' 	=> 	array(
																 								 		 						'parentTable' 	=> 	'account_master',
																 								 		 						'key' 			=> 	'id',
																 								 		 						'description' 	=> 	'&nbsp;'
																 								 	 						)
																 							 	),
																 							array(
																 								 	'name' 			=> 	'plan_id',
																 								 	'description' 	=> 	'Plan selected by the customer for the upgrade.',
																 								 	'arrMapping' 	=> 	array(
																 								 								'parentTable' 	=> 	'plan_master',
																 								 								'key'			=>	'id',
																 								 								'description' 	=> 	'&nbsp;'
																 								 							)
																 							 	),
																 							array(
																 								 	'name' 			=> 	'team_size',
																 								 	'description' 	=> 	'Number of members Customer want to register with this plan'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'currency',
																 								 	'description' 	=> 	'2 Digit Currency Code, Default USD'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'amount',
																 								 	'description' 	=> 	'Calcualted amount of the plan, according to the plan and number of team members customer is registering for'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'credit_balance',
																 								 	'description' 	=> 	'Credit Balance for the customer, Credit used (if any)'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'coupon_id',
																 								 	'description' 	=> 	'If Customer had a saleshandy coupon and he applies into the payment then it\'s id will gets stored over here.',
																 								 	'arrMapping' 	=> 	array(
																 								 		 						'parentTable' 	=> 	'coupon_master',
																 								 		 						'key' 			=>	'id',
																 								 		 						'description' 	=> 	'&nbsp;'
																 								 	 						)
																 							 	),
																 							array(
																 								 	'name' 			=> 	'discount_type',
																 								 	'description' 	=> 	'If Coupon id applied, Then the discount type of the applied coupon : enum (AMT, PER)'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'discount_value',
																 								 	'description' 	=> 	'If Counpon is applied, Then the value of applied coupon discount will come here'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'discount_amount',
																 								 	'description' 	=> 	'If coupon is applied, then the calcualted discount amount will come here.'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'total_amount',
																 								 	'description' 	=> 	'Total amount customer supposed to pay after all calculations.'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'payment_method_id',
																 								 	'description' 	=> 	'What payment method the customer selects',
																 								 	'arrMapping' 	=> 	array(
																 								 		 						'parentTable' 	=> 	'payment_method_master',
																 								 		 						'key' 			=>	'id',
																 								 		 						'description' 	=> 	'&nbsp;'
																 								 	 						)
																 							 	),
																 							array(
																 								 	'name' 			=> 	'start_date',
																 								 	'description' 	=> 	'Since when this subscription\'s related functionality starts, In UNIX Timestamp, TimeZone (+00:00)'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'end_date',
																 								 	'description' 	=> 	'Since when this subscription\'s related functionality ends, In UNIX Timestamp, TimeZone (+00:00)'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'next_subscription_id',
																 								 	'description' 	=> 	'For recurring billing, When customer\'s current plan ends and he subscribe to new plan, then the new subscriptions id i.e primary key will gets stored in this coulumn, so that we can trace the customers billing history.',
																 								 	'arrMapping' 	=>	array(
																 								 								'parentTable' 	=>	'account_subscription_details',
																 								 								'key' 			=>	'id',
																 								 								'description' 	=>	'&nbsp;'
																 								 	 						)
																 							 	),
																 							array(
																 								 	'name' 			=> 	'tp_subscription_id',
																 								 	'description' 	=> 	'Store Payment gateway\'s subscription related information, Payment gateway subscription id'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'tp_customer_id',
																 								 	'description' 	=> 	'Store Payment gateway\'s subscription related information, Payment gateway customer id'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'status',
																 								 	'description' 	=> 	'0-Pending, 1-Success, 2-Fail'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'created',
																 								 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																 							 	),
																 							array(
																 								 	'name' 			=> 	'modified',
																 								 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																 							 	)
															 							)
				 										),
				'account_billing_master' 	=>	array(
														'tableDescription' 	=>	'Master table for billing.',
														'utility' 			=>	'This is the master table for the billing, for a single account there will be one entry in this table.',
														'columnDefination' 	=>	array(
															 							array(
															 									'name' 			=>	'id',
															 									'description'	=>	'Account Billing Id, Primary Key'
															 							 	),
															 							array(
															 									'name' 			=>	'account_id',
															 									'description'	=>	'Connected Account',
															 									'arrMapping' 	=>	array(
															 										 						'parentTable'	=>	'account_master',
															 										 						'key' 			=>	'id',
															 										 						'description' 	=>	'&nbsp;'
															 									 						)
															 							 	),
															 							array(
															 									'name' 			=>	'plan_id',
															 									'description'	=>	'Account current running plan.',
															 									'arrMapping' 	=> 	array(
															 																'parentTable' 	=>	'plan_master',
															 																'key' 			=>	'id',
															 																'description' 	=> 	'&nbsp;'
															 									 						)
															 							 	),
															 							array(
															 									'name' 			=>	'team_size',
															 									'description'	=>	'Team size according to current subscription plan'
															 							 	),
															 							array(
															 									'name' 			=>	'current_subscription_id',
															 									'description'	=>	'Current subscription plan running in customer\'s account',
															 									'arrMapping' 	=>	array(
															 																'parentTable' 	=>	'account_subscription_details',
															 																'key' 			=>	'id',
															 																'description' 	=>	'&nbsp;'
															 									 						)
															 							 	),
															 							array(
															 									'name' 			=>	'next_subscription_updates',
															 									'description'	=>	'JSON string stored in this column, which had information OR changes about the next subscription'
															 							 	),
															 							array(
															 									'name' 			=>	'credit_balance',
															 									'description'	=>	'Customer\'s accuont current credit balance'
															 							 	),
															 							array(
															 									'name' 			=>	'status',
															 									'description'	=>	'0-Inactive, 1-Active, 2-Deleted'
															 							 	),
															 							array(
															 									'name' 			=>	'created',
															 									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
															 							 	),
															 							array(
															 									'name' 			=>	'modified',
															 									'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
															 							 	)
														 							)
				 									),
				'account_payment_details'	=>	array(
														'tableDescription' 	=>	'Payment details for the transaction',
														'utility' 			=>	'This table stores all the payment related details regarding the subscription customer is purchasing.',
														'columnDefination' 	=>	array(
																						array(
																								'name'			=>	'id',
																								'description' 	=>	'Account Payment Detail Id, Primary key'
																						 	),
																						array(
																								'name'			=>	'account_id',
																								'description' 	=>	'Customer\'s account Id.',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_master',
																															'key' 			=>	'id',
																															'description' 	=> 	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name'			=>	'account_subscription_id',
																								'description' 	=>	'Subscription Id for which this payment is going',
																								'arrMapping'	=>	array(
																															'parentTable' 	=>	'account_subscription_details',
																															'key' 			=>	'id',
																															'description'	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name'			=>	'currency',
																								'description' 	=>	'3 Digit Currency Code, Default USD'
																						 	),
																						array(
																								'name'			=>	'amount_paid',
																								'description' 	=>	'After all the calcualtion the total amount customer needs to pay.'
																						 	),
																						array(
																								'name'			=>	'payment_method_id',
																								'description' 	=>	'What payment method custmer selects to pay saleshandy',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'payment_method_master',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																														)
																						 	),
																						array(
																								'name'			=>	'tp_payload',
																								'description' 	=>	''
																						 	),
																						array(
																								'name'			=>	'tp_payment_id',
																								'description' 	=>	''
																						 	),
																						array(
																								'name'			=>	'type',
																								'description' 	=>	'Type of subscription, 1-Plan Subscription, 2-Recurring, 3-Team Size Increase, 4-Upgrade'
																						 	),
																						array(
																								'name'			=>	'paid_at',
																								'description' 	=>	'Payment time stamp, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
																						array(
																								'name'			=>	'status',
																								'description' 	=>	'0-Pending, 1-Success, 2-Fail, 3-Fraud'
																						 	),
																						array(
																								'name'			=>	'created',
																								'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
																						array(
																								'name'			=>	'modified',
																								'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
														 							)
				 									),
				'account_invoice_details' 	=>	array(
														'tableDescription' 	=>	'',
														'utility' 			=>	'',
														'columnDefination' 	=>	array(
																						array(
																								'name' 			=>	'id',
																								'description' 	=>	'Account Invoice Detail Id, Primary key'
																						 	),
																						array(
																								'name' 			=>	'invoice_number',
																								'description' 	=>	'Unique Invoice number generated for the payment transaction'
																						 	),
																						array(
																								'name' 			=>	'account_id',
																								'description' 	=>	'Customer\'s account Id.',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_master',
																															'key' 			=>	'id',
																															'description' 	=> 	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'account_subscription_id',
																								'description' 	=>	'Subscription Id for which this payment is going',
																								'arrMapping'	=>	array(
																															'parentTable' 	=>	'account_subscription_details',
																															'key' 			=>	'id',
																															'description'	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'account_payment_id',
																								'description' 	=>	'Payment Detail tables id',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_payment_details',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'currency',
																								'description'	=>	'3 Digit Currency Code, used for payment, Default we are using USD.',
																						 	),
																						array(
																								'name' 			=>	'amount',
																								'description' 	=>	'Amount According to the plan and number of team members.'
																						 	),
																						array(
																								'name' 			=>	'discount_amount',
																								'description' 	=>	'If Any coupon is applied then the discounted amount'
																						 	),
																						array(
																								'name' 			=>	'credit_amount',
																								'description' 	=>	'If account has any credit associated with it, and how much credit can be used for this transaction'
																						 	),
																						array(
																								'name' 			=>	'total_amount',
																								'description' 	=>	'Final total payable amount for the customer'
																						 	),
																						array(
																								'name' 			=>	'file_copy',
																								'description' 	=>	'File path to invoice, Currently not implemented.'
																						 	),
																						array(
																								'name' 			=>	'created',
																								'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	)
														 							)
				 									),
				'account_companies'		=>	array(
													'tableDescription' 	=>	'Manages the list of companies in the system',
													'utility' 			=>	'Stores and manages all the list of companies for a account. This is used to set group contacts within the company.',
													'columnDefination'	=>	array(
																					array(
																						 	'name' 			=>	'id',
																						 	'description' 	=> 	'Account Company Id, Primary Key'
																					 	),
																					array(
																						 	'name' 			=>	'account_id',
																						 	'description' 	=> 	'Account Id, priamry key. To map this company with the perticular account.',
																						 	'arrMapping' 	=> 	array(
																						 								'parentTable' 	=>	'account_master',
																						 								'key' 			=>	'id',
																						 								'description' 	=>	'&nbsp;'
																						 	 						)
																					 	),
																					array(
																						 	'name' 			=>	'source_id',
																						 	'description' 	=> 	'From which source the company is created, default "WEB_APP"',
																						 	'arrMapping' 	=>	array(
																						 								'parentTable' 	=> 	'source_master',
																						 								'key' 			=>	'id',
																						 								'description' 	=> 	'&nbsp;'
																						 	 						)
																					 	),
																					array(
																						 	'name' 			=>	'name',
																						 	'description' 	=> 	'Name of the company'
																					 	),
																					array(
																						 	'name' 			=>	'address',
																						 	'description' 	=> 	'Address of the company'
																					 	),
																					array(
																						 	'name' 			=>	'city',
																						 	'description' 	=> 	'Name of the city'
																					 	),
																					array(
																						 	'name' 			=>	'state',
																						 	'description' 	=> 	'Name of the state'
																					 	),
																					array(
																						 	'name' 			=>	'country',
																						 	'description' 	=> 	'Name of the country'
																					 	),
																					array(
																						 	'name' 			=>	'zipcode',
																						 	'description' 	=> 	'Zip Code of the company'
																					 	),
																					array(
																						 	'name' 			=>	'logo',
																						 	'description' 	=> 	'File path of the company logo'
																					 	),
																					array(
																						 	'name' 			=>	'website',
																						 	'description' 	=> 	'Website of the company'
																					 	),
																					array(
																						 	'name' 			=>	'contact_phone',
																						 	'description' 	=> 	'Contact Phone of the company'
																					 	),
																					array(
																						 	'name' 			=>	'contact_fax',
																						 	'description' 	=> 	'Contact Fax number of the company'
																					 	),
																					array(
																						 	'name' 			=>	'short_info',
																						 	'description' 	=> 	'Short description about the company'
																					 	),
																					array(
																						 	'name' 			=>	'total_mail_sent',
																						 	'description' 	=> 	'Total Mail sent to the contacts within this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_mail_failed',
																						 	'description' 	=> 	'Total mail failed by the server for the contacts of this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_mail_replied',
																						 	'description' 	=> 	'Total mail replaed by the contacts of this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_mail_bounced',
																						 	'description' 	=> 	'Total mails bounced to the contacts of this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_link_clicks',
																						 	'description' 	=> 	'Total link clicked by the contacts of this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_document_viewed',
																						 	'description' 	=> 	'Total Document viewed by the contacts of this company'
																					 	),
																					array(
																						 	'name' 			=>	'total_document_facetime',
																						 	'description' 	=> 	'Total time spent on the documents by the contacts of the company. (In Seconds)'
																					 	),
																					array(
																						 	'name' 			=>	'status',
																						 	'description' 	=> 	'0-Inactive, 1-Active, 2-Deleted, 3-Blocked'
																					 	),
																					array(
																						 	'name' 			=>	'created',
																						 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																						 	'name' 			=>	'modified',
																						 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	)
													 							)
				 								),
				'account_contacts' 	=>	array(
												'tableDescription' 	=> 	'',
												'utility' 			=>	'',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=> 	'Account Contact Id, Primary Key',
																				 	),
																				array(
																						'name' 			=>	'account_id',
																						'description' 	=> 	'Account id with which the contact is linked',
																						'arrMapping' 	=>	array(
																							 						'parentTable' 	=> 	'account_master',
																							 						'key' 			=>	'id',
																							 						'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'account_company_id',
																						'description' 	=> 	'Id of the Company with which this account is associated.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_companies',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'source_id',
																						'description' 	=> 	'Source Id, from which the contact has been created',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'source_master',
																													'key' 			=>	'id',
																													'description' 	=> 	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'email',
																						'description' 	=> 	'Email address of the contact',
																				 	),
																				array(
																						'name' 			=>	'first_name',
																						'description' 	=> 	'First Name of the contact',
																				 	),
																				array(
																						'name' 			=>	'last_name',
																						'description' 	=> 	'Last name of the contact',
																				 	),
																				array(
																						'name' 			=>	'total_mail_sent',
																						'description' 	=> 	'Total mail sent to this contact',
																				 	),
																				array(
																						'name' 			=>	'total_mail_failed',
																						'description' 	=> 	'Total mail failed to this contact',
																				 	),
																				array(
																						'name' 			=>	'total_mail_replied',
																						'description' 	=> 	'Total mail replied by this contact',
																				 	),
																				array(
																						'name' 			=>	'total_mail_bounced',
																						'description' 	=> 	'Total mail bounced for this contact',
																				 	),
																				array(
																						'name' 			=>	'total_link_clicks',
																						'description' 	=> 	'Total links clicked by this contact',
																				 	),
																				array(
																						'name' 			=>	'total_document_viewed',
																						'description' 	=> 	'Total number of document\'s viewed by this contact',
																				 	),
																				array(
																						'name' 			=>	'total_document_face',
																						'description' 	=> 	'Total facetime this contact viewed all the shared documents.',
																				 	),
																				array(
																						'name' 			=>	'status',
																						'description' 	=> 	'0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
																				 	),
																				array(
																						'name' 			=>	'created',
																						'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'account_folders' 	=>	array(
												'tableDescription' 	=>	'Manages all the types of folders in the system',
												'utility' 			=>	'',
												'columnDefination'	=>	array(
																				array(
																					 	'name' 			=>	'id',
																					 	'description' 	=> 	'Account Folder Id, Primary key'
																				 	),
																				array(
																					 	'name' 			=>	'account_id',
																					 	'description' 	=> 	'Account Id of the team member that created the folder',
																					 	'arrMapping' 	=>	array(
																					 								'parentTable' 	=>	'account_master',
																					 								'key'			=>	'id',
																					 								'description' 	=> 	'&nbsp;'
																					 	 						)
																				 	),
																				array(
																					 	'name' 			=>	'user_id',
																					 	'description' 	=> 	'Id of the team member who created this folder',
																					 	'arrMapping' 	=>	array(
																					 								'parentTable' 	=>	'user_master',
																					 								'key' 			=>	'id',
																					 								'description' 	=> 	'&nbsp;'
																					 	 						)
																				 	),
																				array(
																					 	'name' 			=>	'source_id',
																					 	'description' 	=> 	'Source from where this folder has been created',
																					 	'arrMapping' 	=> 	array(
																					 								'parentTable' 	=>	'source_master',
																					 								'key' 			=>	'id',
																					 								'description' 	=>	'&nbsp;'
																					 	 						)
																				 	),
																				array(
																					 	'name' 			=>	'name',
																					 	'description' 	=> 	'Name of the folder'
																				 	),
																				array(
																					 	'name' 			=>	'type',
																					 	'description' 	=> 	'Folder type, 1-Templates, 2-Documents'
																				 	),
																				array(
																					 	'name' 			=>	'public',
																					 	'description' 	=> 	'Wether this folder is public or not with this team. 0-No, 1-Yes'
																				 	),
																				array(
																					 	'name' 			=>	'share_access',
																					 	'description' 	=> 	'Shared users access rights, stored in JSON string format.'
																				 	),
																				array(
																					 	'name' 			=>	'status',
																					 	'description' 	=> 	'1-Active, 2-Deleted'
																				 	),
																				array(
																					 	'name' 			=>	'created',
																					 	'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																					 	'name' 			=>	'modified',
																					 	'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'account_templates'		=> array(
													'tableDescription'	=>	'Master table for tempaltes',
													'utility' 			=>	'Stores and manages all the templates in the system account wise.',
													'columnDefination'	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Account Template Id, Primary Key'
																					 	),
																					array(
																							'name' 			=>	'account_id',
																							'description' 	=>	'Account Is of the team member, who has created this template',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'user_id',
																							'description' 	=>	'Id of the team member who had created this template',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'user_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'source_id',
																							'description' 	=>	'Source from where this template is created',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'source_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'title',
																							'description' 	=>	'Title of the template'
																					 	),
																					array(
																							'name' 			=>	'subject',
																							'description' 	=>	'Subject line of the template'
																					 	),
																					array(
																							'name' 			=>	'content',
																							'description' 	=>	'Template content'
																					 	),
																					array(
																							'name' 			=>	'total_mail_usage',
																							'description' 	=>	'In how many mails this template is used.'
																					 	),
																					array(
																							'name' 			=>	'total_mail_open',
																							'description' 	=>	'Total how many mail\'s opened with this tempate content'
																					 	),
																					array(
																							'name' 			=>	'total_campaign_usage',
																							'description' 	=>	'In how many campains this template is used'
																					 	),
																					array(
																							'name' 			=>	'total_campaign_mails',
																							'description' 	=>	'Total how many mails send with template in campains'
																					 	),
																					array(
																							'name' 			=>	'total_campaign_open',
																							'description' 	=>	'???????????????'
																					 	),
																					array(
																							'name' 			=>	'last_used_at',
																							'description' 	=>	'When the template is last used, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'public',
																							'description' 	=>	'0-No, 1-Yes'
																					 	),
																					array(
																							'name' 			=>	'share_access',
																							'description' 	=>	'Shared users access rights'
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'0-Inactive, 1-Active, 2-Deleted'
																					 	),
																					array(
																							'name' 			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
													 							)
				 								),


				'account_template_folders'	=>	array(
														'tableDescription' 	=>	'This is the mapping table for template',
														'utility' 			=>	'This table stores the mapping between the account_folders and account_templates.',
														'columnDefination' 	=>	array(
																						array(
																								'name'			=>	'id',
																								'description' 	=> 	'Account Template Folder Id, Primary Key'
																						 	),
																						array(
																								'name'			=>	'account_template_id',
																								'description' 	=> 	'Id of the account template table',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_templates',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name'			=>	'account_folder_id',
																								'description' 	=> 	'Id of the account folder table',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_folders',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name'			=>	'status',
																								'description' 	=> 	'1-Active, 2-Deleted'
																						 	),
																						array(
																								'name'			=>	'modified',
																								'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	)
														 							)
				 									),
				'account_template_teams'	=>	array(
														'tableDescription' 	=>	'Manages all the tempate sharing within team.',
														'utility' 			=>	'This table stores all the template mapping with the teams with in an account.',
														'columnDefination'	=>	array(
																						array(
																								'name' 			=>	'id',
																								'description' 	=>	'Account Temaplte Teams Id, Primary key'
																						 	),
																						array(
																								'name' 			=>	'account_template_id',
																								'description' 	=>	'Id of master temaplte table. i.e "account_templates"',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_templates',
																															'key' 			=>	'id',
																															'description' 	=> 	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'account_team_id',
																								'description' 	=>	'Id of the team to which this tempalte needs to be shared',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_teams',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'status',
																								'description' 	=>	'1-Active, 2-Deleted'
																						 	),
																						array(
																								'name' 			=>	'modified',
																								'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	)
														 							)
				 									),
				'account_folder_teams' 	=>	array(
													'tableDescription' 	=>	'',
													'utility' 			=>	'',
													'columnDefination' 	=> 	array(
																					array(
																							'name' 			=>	'id',
																							'description'	=>	'Account Folder Team id, primary key'
																					 	),
																					array(
																							'name' 			=>	'account_folder_id',
																							'description'	=>	'Folder\'s id that needs to be shared with the team',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_folders',
																														'key' 			=>	'id',
																														'description' 	=> 	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'account_team_id',
																							'description'	=>	'Id of the team to whome member is sharing the folder.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_teams',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description'	=>	'1-Active, 2-Deleted'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
													 							)
				 								),

				'document_master' 		=>	array(
													'tableDescription' 	=>	'This table stores all the uploaded documents in system by the team members.',
													'utility' 			=>	'This table stores all the uploaded documents in system by the team members.',
													'columnDefination' 	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Document Master Id, Primary key'
																					 	),
																					array(
																							'name' 			=>	'account_id',
																							'description' 	=>	'Account Id of team member who uploaded the document',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'user_id',
																							'description' 	=>	'Team member Id, who is upload the document',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'user_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'document_source_id',
																							'description' 	=>	'Id of the document source from where the document is being uploaded.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'document_source_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'source_id',
																							'description' 	=>	'Source from where the document is being uploaded',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'source_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'file_path',
																							'description' 	=>	'File path of the document'
																					 	),
																					array(
																							'name' 			=>	'file_type',
																							'description' 	=>	'Type of document, DOC, DOCX, PDF, PPT etc.'
																					 	),
																					array(
																							'name' 			=>	'file_pages',
																							'description' 	=>	'Total number of pages in the document'
																					 	),
																					array(
																							'name' 			=>	'source_document_id',
																							'description' 	=>	'ID of document if imported from any source'
																					 	),
																					array(
																							'name' 			=>	'source_document_link',
																							'description' 	=>	'Link of document if imported from any source'
																					 	),
																					array(
																							'name' 			=>	'public',
																							'description' 	=>	'0-No, 1-Yes'
																					 	),
																					array(
																							'name' 			=>	'share_access',
																							'description' 	=>	'Shared users access rights, in JSON formatted string'
																					 	),
																					array(
																							'name' 			=>	'snooze_notifications',
																							'description' 	=>	'To turn on/off snooze notification for this docuement every where. 0-No, 1-Yes'
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'0-Inactive, 1-Active, 2-Deleted'
																					 	),
																					array(
																							'name' 			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	)
													 							)
				 								),
				'document_folders'		=>	array(
													'tableDescription' 	=>	'This folder manages all the relation ship between the documents and folder.',
													'utility' 			=>	'This folder manages all the relation ship between the documents and folder.',
													'columnDefination' 	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Document folder Id',
																					 	),
																					array(
																							'name' 			=>	'document_id',
																							'description' 	=>	'Docuement\'s tables id',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'document_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'account_folder_id',
																							'description' 	=>	'Id of the master table of the folder\'s "account_folders".',
																							'arrMapping'	=>	array(
																														'parentTable' 	=>	'account_folders',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
 																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'1-Active, 2-Deleted',
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
													 							)
				 								),
				'document_teams'	=>	array(
												'tableDescription' 	=>	'This table stores the relation of how documets are shared between the teams.',
												'utility' 			=>	'',
												'columnDefination' 	=>	array(
																				array(
																						'name'			=>	'id',
																						'description' 	=>	'Document Teams Id, Primary Key',
																				 	),
																				array(
																						'name'			=>	'document_id',
																						'description' 	=>	'This is the primary key of the document_master table',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'document_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name'			=>	'account_team_id',
																						'description' 	=>	'Team id\'s for which this docuement is shared.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_teams',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name'			=>	'status',
																						'description' 	=>	'1-Active, 2-Deleted',
																				 	),
																				array(
																						'name'			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
												 							)
				 							),
				'document_links'	=>	array(
												'tableDescription' 	=>	'asfasdf',
												'utility'			=>	'asdfasfdasd',
												'columnDefination' 	=>	array(
																			array(
																					'name' 			=>	'id',
																					'description' 	=>	'Document Link Id, primary key'
													 							),
																			array(
																					'name' 			=>	'account_id',
																					'description' 	=>	'Account id of the team member who has created the document link',
													 							),
																			array(
																					'name' 			=>	'user_id',
																					'description' 	=>	'Id of the team member, who is creating the link',
													 							),
																			array(
																					'name' 			=>	'source_id',
																					'description' 	=>	'Source from where the document link is getting created',
													 							),
																			array(
																					'name' 			=>	'account_company_id',
																					'description' 	=>	'Id of the company of the contact.',
																					'arrMapping' 	=>	array(
																												'parentTable' 	=>	'account_companies',
																												'key' 			=>	'id',
																												'description' 	=>	'&nbsp;'
																					 						)
													 							),
																			array(
																					'name' 			=>	'account_contact_id',
																					'description' 	=>	'Id of the contact to which we are sending the document link',
																					'arrMapping' 	=>	array(
																												'parentTable' 	=>	'account_contacts',
																												'key' 			=>	'id',
																												'description' 	=>	'&nbsp;'
																					 						)
													 							),
																			array(
																					'name' 			=>	'name',
																					'description' 	=>	'Name of the document link',
													 							),
																			array(
																					'name' 			=>	'short_description',
																					'description' 	=>	'Short description of the document link.',
													 							),
																			array(
																					'name' 			=>	'link_domain',
																					'description' 	=>	'Link for the document tracking',
													 							),
																			array(
																					'name' 			=>	'link_code',
																					'description' 	=>	'Unique identifier that will be attache to the document link URL.',
													 							),
																			array(
																					'name' 			=>	'type',
																					'description' 	=>	'1-Document, 2-Folder',
													 							),
																			array(
																					'name' 			=>	'is_set_expiration_date',
																					'description' 	=>	'Wether the link will expire or not.',
													 							),
																			array(
																					'name' 			=>	'expires_at',
																					'description' 	=>	'Document link expiration date, In UNIX Timestamp, TimeZone (+00:00)',
													 							),
																			array(
																					'name' 			=>	'allow_download',
																					'description' 	=>	'Flag to allow files to be downloaded. 0-No, 1-Yes',
													 							),
																			array(
																					'name' 			=>	'password_protected',
																					'description' 	=>	'Flag to set password protected.0-No, 1-Yes',
													 							),
																			array(
																					'name' 			=>	'access_password',
																					'description' 	=>	'Set password for password proteched document links',
													 							),
																			array(
																					'name' 			=>	'ask_visitor_info',
																					'description' 	=>	'Flag to ask vistiors information when document link was opened. 0-No, 1-Yes',
													 							),
																			array(
																					'name' 			=>	'visitor_info_payload',
																					'description' 	=>	'If visitor is filling it\'s information then visitors info paylod will be set over here. in JSON String format.',
													 							),
																			array(
																					'name' 			=>	'snooze_notifications',
																					'description' 	=>	'To turn on and off the snooz notification for the perticular docuement link, 0-No, 1-Yes',
													 							),
																			array(
																					'name' 			=>	'remind_not_viewed',
																					'description' 	=>	'????????????/ 0-No, 1-Yes, Reminds team member about customer hasent been seen the document yet. ',
													 							),
																			array(
																					'name' 			=>	'remind_at',
																					'description' 	=>	'If customer has not seent the document link ans team member had set notification for reminder then time stamp value will be stored in this system., In UNIX Timestamp, TimeZone (+00:00)',
													 							),
																			array(
																					'name' 			=>	'status',
																					'description' 	=>	'0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
													 							),
																			array(
																					'name' 			=>	'created',
																					'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
													 							),
																			array(
																					'name' 			=>	'modified',
																					'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
													 							)
																		)
				 							),
				'document_link_files' 	=>	array(
													'tableDescription' 	=>	'',
													'utility' 			=>	'',
													'columnDefination' 	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Document Link Files Id, Primary Key'
																					 	),
																					array(
																							'name' 			=>	'document_link_id',
																							'description' 	=>	'Id of the document link table. This id shows that this file linked with which document link.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'document_links',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'document_id',
																							'description' 	=>	'Document\'s master table id.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'document_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'account_folder_id',
																							'description' 	=>	'???????????????????'
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'1-Active, 2-Deleted'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),

													 							)
				 								),
				'email_sending_method_master' 	=>	array(
															'tableDescription' 	=>	'Manages all the email sending options for the team members',
															'utility' 			=>	'Manages all the email sending options for the team members',
															'columnDefination' 	=>	array(
																							array(
																									'name' 			=>	'id',
																									'description' 	=>	'Email Sending Method Master id, Primary key'
																							 	),
																							array(
																									'name' 			=>	'code',
																									'description' 	=>	'Unique Identifier for the email sending method'
																							 	),
																							array(
																									'name' 			=>	'name',
																									'description' 	=>	'Name of the email sending method'
																							 	),
																							array(
																									'name' 			=>	'status',
																									'description' 	=>	'0-Inactive, 1-Active'
																							 	),
																							array(
																									'name' 			=>	'created',
																									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																							 	),
																							array(
																									'name' 			=>	'modified',
																									'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																							 	),
															 							)
				 										),
 				'account_sending_methods' 	=>	array(
														'tableDescription' 	=>	'This table manages all the email accounts of the team members',
														'utility' 			=>	'This table manages all the email accounts of the team members',
														'columnDefination' 	=>	array(
																						array(
																								'name' 			=>	'id',
																								'description' 	=>	'Account sending methods Id, Primary key'
																						 	),
																						array(
																								'name' 			=>	'account_id',
																								'description' 	=>	'Account id of the team member who creates the email account',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'account_master',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'user_id',
																								'description' 	=>	'Id of the team member which is creating the email account',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'user_master',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'email_sending_method_id',
																								'description' 	=>	'Which email settings team member is going to use for this email account like Gmail or SMTP',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'email_sending_method_master',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'source_id',
																								'description' 	=>	'From where this email account has been created.',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'source_master',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name' 			=>	'name',
																								'description' 	=>	'Name of the email account'
																						 	),
																						array(
																								'name' 			=>	'from_name',
																								'description' 	=>	'From Name, while sending email'
																						 	),
																						array(
																								'name' 			=>	'from_email',
																								'description' 	=>	'From Email, while sending the email'
																						 	),
																						array(
																								'name' 			=>	'payload',
																								'description' 	=>	'??????????????/'
																						 	),
																						array(
																								'name' 			=>	'incoming_payload',
																								'description' 	=>	'??????????????'
																						 	),
																						array(
																								'name' 			=>	'connection_status',
																								'description' 	=>	'0-Invalid, 1-Valid'
																						 	),
																						array(
																								'name' 			=>	'connection_info',
																								'description' 	=>	'Informatin about the status of the connection. ?????????????'
																						 	),
																						array(
																								'name' 			=>	'last_connected_at',
																								'description' 	=>	'When this email address is connected last time with the corresponding server, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
																						array(
																								'name' 			=>	'last_error',
																								'description' 	=>	'Description about the last error occured.'
																						 	),
																						array(
																								'name' 			=>	'total_mail_sent',
																								'description' 	=>	'Total number of mail sent with this email account'
																						 	),
																						array(
																								'name' 			=>	'total_mail_failed',
																								'description' 	=>	'Total mail failed with this email account.'
																						 	),
																						array(
																								'name' 			=>	'total_mail_replied',
																								'description' 	=>	'Total email replied back with this email account.'
																						 	),
																						array(
																								'name' 			=>	'total_mail_bounced',
																								'description' 	=>	'Total mail bounced with this email account.'
																						 	),
																						array(
																								'name' 			=>	'public',
																								'description' 	=>	'To make this account accessible to all the team members, 0-No, 1-Yes'
																						 	),
																						array(
																								'name' 			=>	'total_limit',
																								'description' 	=>	'Total Limit of the email account set by saleshandy according to the plan.'
																						 	),
																						array(
																								'name' 			=>	'credit_limit',
																								'description' 	=>	'?????????'
																						 	),
																						array(
																								'name' 			=>	'last_reset',
																								'description' 	=>	'?????????????????'
																						 	),
																						array(
																								'name' 			=>	'next_reset',
																								'description' 	=>	'????????????????'
																						 	),
																						array(
																								'name' 			=>	'status',
																								'description' 	=>	'0-Inactive, 1-Active, 2-Deleted, 3-Blocked'
																						 	),
																						array(
																								'name' 			=>	'created',
																								'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
																						array(
																								'name' 			=>	'modified',
																								'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																						 	)
														 							)
				 									),
				'account_link_master' 	=>	array(
													'tableDescription' 	=>	'Stores all the links those are used by team members of different accounts.',
													'utility' 			=>	'Stores all the links those are used by team members of different accounts.',
													'columnDefination'	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Account Link Master Id, Primary key',
																					 	),
																					array(
																							'name' 			=>	'account_id',
																							'description' 	=>	'Account Id of team member which is using the link in email content',
																					 	),
																					array(
																							'name' 			=>	'url',
																							'description' 	=>	'Url',
																					 	),
																					array(
																							'name' 			=>	'redirect_key',
																							'description' 	=>	'Not in USED',
																					 	),
																					array(
																							'name' 			=>	'total_clicked',
																							'description' 	=>	'Total Number of clicks in the URL from the mail content',
																					 	),
																					array(
																							'name' 			=>	'last_clicked',
																							'description' 	=>	'When this link was clicked last, In UNIX Timestamp, TimeZone (+00:00)',
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'1-Active, 2-Deleted, 3-Blocked',
																					 	),
																					array(
																							'name' 			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	)
													 							)
				 								),
				'email_master' 	=>	array(
					 						'tableDescription' 	=>	'Master table for all the emails sends from the system.',
					 						'utility' 			=>	'Master table for all the emails sends from the system.',
					 						'columnDefination' 	=>	array(
					 							 							array(
					 							 									'name' 			=>	'id',
					 							 									'description' 	=>	'Email Master Id, Primary Key',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'account_id',
					 							 									'description' 	=>	'Account id of team member whos is sending this email.',
					 							 									'arrMapping' 	=>	array(
					 							 																'parentTable' 	=>	'account_master',
					 							 																'key' 			=>	'id',
					 							 																'description' 	=>	'&nbsp;'
					 							 									 						)
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'user_id',
					 							 									'description' 	=>	'Primary key of the team member who is sending this email',
					 							 									'arrMapping' 	=>	array(
					 							 																'parentTable' 	=>	'user_master',
					 							 																'key' 			=>	'id',
					 							 																'description' 	=> 	'&nbsp;'
					 							 									 						)
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'account_template_id',
					 							 									'description' 	=>	'If user has selected any template for this email.',
					 							 									'arrMapping' 	=>	array(
					 							 																'parentTable' 	=>	'account_templates',
					 							 																'key' 			=>	'column',
					 							 																'description' 	=>	'&nbsp;'
					 							 									 						)
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'account_sending_method_id',
					 							 									'description' 	=>	'By which method the team member is sending this email.',
					 							 									'arrMapping' 	=>	array(
					 							 																'parentTable' 	=>	'account_sending_methods',
					 							 																'key' 			=>	'id',
					 							 																'description' 	=>	'&nbsp;'
					 							 									 						)
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'source_id',
					 							 									'description' 	=>	'From which source team member is using for sending this email.',
					 							 									'arrMapping' 	=>	array(
					 							 																'parentTable' 	=>	'source_master',
					 							 																'key' 			=>	'id',
					 							 																'description' 	=>	'&nbsp;'
					 							 									 						)
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'subject',
					 							 									'description' 	=>	'Subject of the mail',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'content',
					 							 									'description' 	=>	'Mail content',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'is_scheduled',
					 							 									'description' 	=>	'Flag to set wether this mail is scheduled or needs to send instantly. 0-No, 1-Yes',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'scheduled_at',
					 							 									'description' 	=>	'If email is scheduled then at when the email will trigger, In UNIX Timestamp, TimeZone (+00:00)',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'timezone',
					 							 									'description' 	=>	'If email is scheduled then at what time zone it should trigger.',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'sent_at',
					 							 									'description' 	=>	'Time stamp when email actually fired, In UNIX Timestamp, TimeZone (+00:00)',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'track_reply',
					 							 									'description' 	=>	'Flag to check, wether to track this email\'s reply or not. 0-No, 1-Yes',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'track_click',
					 							 									'description' 	=>	'Flag to check, wether to track all the links in the email content or not. 0-No, 1-Yes',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'cc',
					 							 									'description' 	=>	'To send this email in CC to any other Email.',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'bc',
					 							 									'description' 	=>	'To send this email in BCC to any other Email.',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'total_recipients',
					 							 									'description' 	=>	'Total number of receipents this email',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'snooze_notifications',
					 							 									'description' 	=>	'Wether to give push notifcation to the customer about this email has opened. 0-No, 1-Yes',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'progress',
					 							 									'description' 	=>	'States of the email, 0-Scheduled, 1-Sent, 2-Fail',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'status',
					 							 									'description' 	=>	'0-Draft, 1-Active, 2-Deleted',
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'created',
					 							 									'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
					 							 							 	),
					 							 							array(
					 							 									'name' 			=>	'modified',
					 							 									'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
					 							 							 	)
					 						 							)
				 						),
				'email_recipients' 	=>	array(
												'tableDescription' 	=>	'This table stores all the information about all the receipents of the email. this table also stores all the statistical information also.',
												'utility' 			=>	'This table stores all the information about all the receipents of the email. this table also stores all the statistical information also.',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Email Receipients Id, Primary Key'
																				 	),
																				array(
																						'name' 			=>	'account_id',
																						'description' 	=>	'Account id of the team member who is sending this email. why do we need this column ?????????????',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'email_id',
																						'description' 	=>	'Email id of the receipent'
																				 	),
																				array(
																						'name' 			=>	'account_contact_id',
																						'description' 	=>	'Id of the contact that is associated with this email for this account number.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_contacts',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'open_count',
																						'description' 	=>	'Total how may times this emai has been opened.'
																				 	),
																				array(
																						'name' 			=>	'last_opened',
																						'description' 	=>	'When was the last time this email opened, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'replied',
																						'description' 	=>	'Wether the receipient replied to the mail or not. 0-No, 1-Yes'
																				 	),
																				array(
																						'name' 			=>	'replied_at',
																						'description' 	=>	'When did the receipient replied to the mail.'
																				 	),
																				array(
																						'name' 			=>	'click_count',
																						'description' 	=>	'why?????????????'
																				 	),
																				array(
																						'name' 			=>	'last_clicked',
																						'description' 	=>	'why????????????? what is the use of this column if last_opened is there'
																				 	),
																				array(
																						'name' 			=>	'sent_message_id',
																						'description' 	=>	'Id of message sent (received from mail server)'
																				 	),
																				array(
																						'name' 			=>	'sent_response',
																						'description' 	=>	'Mail sending attempt response from mail server'
																				 	),
																				array(
																						'name' 			=>	'is_bounce',
																						'description' 	=>	'0-No, 1-Yes'
																				 	),
																				array(
																						'name' 			=>	'progress',
																						'description' 	=>	'0-Scheduled, 1-Sent, 2-Fail'
																				 	),
																				array(
																						'name' 			=>	'status',
																						'description' 	=>	'1-Active, 2-Deleted'
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'email_links' 	=>	array(
											'tableDescription' 	=>	'Mapping table for links which are associated with the mail.',
											'utility' 			=>	'Mapping table for links which are associated with the mail.',
											'columnDefination' 	=>	array(
																			array(
																					'name' 			=>	'id',
																					'description' 	=>	'Email Links Id, Primary Key'
																			 	),
																			array(
																					'name' 			=>	'account_id',
																					'description' 	=>	'Account id of the team member which had created the email account.why do we need this column ?????????????',
																					'arrMapping'	=>	array(
																												'parentTable' 	=>	'account_master',
																												'key' 			=>	'id',
																												'description' 	=>	'&nbsp;'
																					 						)
																			 	),
																			array(
																					'name' 			=>	'email_id',
																					'description' 	=>	'Id of the email master table.',
																					'arrMapping' 	=>	array(
																												'parentTable' 	=>	'email_master',
																												'key' 			=>	'id',
																												'description' 	=>	'&nbsp;'
																					 						)
																			 	),
																			array(
																					'name' 			=>	'account_contact_id',
																					'description' 	=>	'Contact associated with this email id for this account.',
																					'arrMapping' 	=>	array(
																												'parentTable' 	=>	'account_contacts',
																												'key' 			=>	'id',
																												'description' 	=>	'&nbsp;'
																					 						)
																			 	),
																			array(
																					'name' 			=>	'account_link_id',
																					'description' 	=>	'Id of the master link table with this account.',
																					'arrMapping' 	=>	array(
																												'parentTable' 	=>	'account_link_master',
																												'key' 			=>	'id',
																												'description1' 	=>	'&nbsp;'
																					 						)
																			 	),
																			array(
																					'name' 			=>	'redirect_key',
																					'description' 	=>	'A unique hashid is generated for this account link'
																			 	),
																			array(
																					'name' 			=>	'total_clicked',
																					'description' 	=>	'Total time the linked is clicked or opened by the receipient'
																			 	),
																			array(
																					'name' 			=>	'last_clicked',
																					'description' 	=>	'When did the last time receipient opened this link'
																			 	),
																			array(
																					'name' 			=>	'modified',
																					'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																			 	)
											 							)
				 						),
				'email_track_history'	=>	array(
													'tableDescription' 	=>	'This table is used for recording all the activities happening on that email',
													'utility' 			=>	'This table is used for recording all the activities happening on that email',
													'columnDefination' 	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Email Track History Id, Primary Key'
																					 	),
																					array(
																							'name' 			=>	'email_recipient_id',
																							'description' 	=>	'Receipient Id to whome opened the link.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'email_recipients',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'type',
																							'description' 	=>	'1-Mail Open, 2-Mail Reply, 3-Link Click'
																					 	),
																					array(
																							'name' 			=>	'acted_at',
																							'description' 	=>	'When the action is taken on the link, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'account_link_id',
																							'description' 	=>	'Id of the account link master table. why????????????',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_link_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	)
													 							)
				 								),
				'campaign_master' 	=>	array(
												'tableDescription' 	=>	'This is the master table for the template',
												'utility' 			=>	'This is the master table for the template',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Campain Master Id, Primary Key',
																				 	),
																				array(
																						'name' 			=>	'account_id',
																						'description' 	=>	'Account Id of the team member who has created the campaign.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'user_id',
																						'description' 	=>	'Id of the team member who is creating the email campaign',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'user_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'title',
																						'description' 	=>	'Title of the email campaign',
																				 	),
																				array(
																						'name' 			=>	'account_sending_method_id',
																						'description' 	=>	'Account sending method selected for sending this mail.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_sending_methods',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'source_id',
																						'description' 	=>	'Id of the source from where the email campain is created.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'source_master',
																													'key' 			=>	'id',
																													'description'	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'total_stages',
																						'description' 	=>	'Total number of stages selected in campaign',
																				 	),
																				array(
																						'name' 			=>	'from_date',
																						'description' 	=>	'Start date of the campaing, In UNIX Timestamp, TimeZone (+00:00)',
																				 	),
																				array(
																						'name' 			=>	'to_date',
																						'description' 	=>	'End date of the campaing, In UNIX Timestamp, TimeZone (+00:00)',
																				 	),
																				array(
																						'name' 			=>	'timezone',
																						'description' 	=>	'Time zone on which this campain will run.',
																				 	),
																				array(
																						'name' 			=>	'other_data',
																						'description' 	=>	'JSON formatted string containing all the all the campaign configuration',
																				 	),
																				array(
																						'name' 			=>	'status_message',
																						'description' 	=>	'Message describing status change of campaign. ???????????',
																				 	),
																				array(
																						'name' 			=>	'track_reply',
																						'description' 	=>	'Flag to set wether need to track reply for this email campaigns. 0-No, 1-Yes',
																				 	),
																				array(
																						'name' 			=>	'track_click',
																						'description' 	=>	'Flag to set wether need to track links for this email campaigns. 0-No, 1-Yes',
																				 	),
																				array(
																						'name' 			=>	'send_as_reply',
																						'description' 	=>	'Flag to send the followup mails in the thread of the parent stage. 0-No, 1-Yes',
																				 	),
																				array(
																						'name' 			=>	'overall_progress',
																						'description' 	=>	'0-Scheduled, 1-Queued, 2-In Progress, 3-Paused, 4-Waiting, 5-Hault, 6-Finish',
																				 	),
																				array(
																						'name' 			=>	'priority',
																						'description' 	=>	'0-Low, 1-Medium, 2-High. HOW WE ARE USING IT IN SYSTEM.??????????????',
																				 	),
																				array(
																						'name' 			=>	'snooze_notifications',
																						'description' 	=>	'To turn on/off push notifications in the app for this email campaign.',
																				 	),
																				array(
																						'name' 			=>	'status',
																						'description' 	=>	'0-Draft, 1-Active, 2-Deleted',
																				 	),
																				array(
																						'name' 			=>	'created',
																						'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'campaign_stages' 	=>	array(
												'tableDescription' 	=>	'This table stores the information of the stages of email campaign',
												'utility' 			=>	'This table stores the information of the stages of email campaign',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Campaign Stages Id, Primary Key'
																				 	),
																				array(
																						'name' 			=>	'account_id',
																						'description' 	=>	'Account Id of the team member who has created the email campaign.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_master',
																													'key' 			=>	'id',
																													'description' 	=> 	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'user_id',
																						'description' 	=>	'Id of the team member who has created the email campaign.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'user_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_id',
																						'description' 	=>	'Parent campaign id',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'subject',
																						'description' 	=>	'Subject of the email for this stage.'
																				 	),
																				array(
																						'name' 			=>	'content',
																						'description' 	=>	'Email content of the email for this stage.'
																				 	),
																				array(
																						'name' 			=>	'account_template_id',
																						'description' 	=>	'Id of the email template If any has been used in this stage of email campaign.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_templates',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'stage',
																						'description' 	=>	'Stage Number, Stage Index.'
																				 	),
																				array(
																						'name' 			=>	'stage_defination',
																						'description' 	=>	'Stage related definations. ?????????????'
																				 	),
																				array(
																						'name' 			=>	'scheduled_on',
																						'description' 	=>	'When this stage of the campaign is scheduled, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'progress',
																						'description' 	=>	'Different stages of the stages of a campaign, 0-Scheduled, 1-Queued, 2-In Progress, 3-Paused, 4-Waiting, 5-Hault, 6-Finish'
																				 	),
																				array(
																						'name' 			=>	'total_contacts',
																						'description' 	=>	'Total number of receipients present in this stage'
																				 	),
																				array(
																						'name' 			=>	'total_success',
																						'description' 	=>	'Total number of mails sends successfully in this stage.'
																				 	),
																				array(
																						'name' 			=>	'total_fail',
																						'description' 	=>	'Total number of mails failed to send successfully in this stage.'
																				 	),
																				array(
																						'name' 			=>	'total_deleted',
																						'description' 	=>	'Total number of receipients removed from the stage after setting the stage.'
																				 	),
																				array(
																						'name' 			=>	'started_on',
																						'description' 	=>	'When this stage\'s execution is started, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'finished_on',
																						'description' 	=>	'When this stage\'s execution is finished, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'report_sent',
																						'description' 	=>	'Flag to identify the status of the report send. 0-Not Sent, 1-Stage Finish Report Sent, 2-Stage Performance Report Sent (To WHOME ??????????????)'
																				 	),
																				array(
																						'name' 			=>	'status',
																						'description' 	=>	'1-Active, 2-Deleted'
																				 	),
																				array(
																						'name' 			=>	'created',
																						'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'campaign_sequences'	=>	array(
													'tableDescription' 	=>	'This table stores the campaign sequences, It manages list of all the receipients for the stages of a campaign.',
													'utility' 			=>	'This table stores the campaign sequences, It manages list of all the receipients for the stages of a campaign.',
													'columnDefination' 	=>	array(
																					array(
																							'name' 			=>	'id',
																							'description' 	=>	'Campaign Sequence Id, Primary Key'
																					 	),
																					array(
																							'name' 			=>	'campaign_id',
																							'description' 	=>	'Id of the campain master, under which this sequence is.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'campaign_master',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'campaign_stage_id',
																							'description' 	=>	'Id of the campaign stage under which this sequence is.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'campaign_stages',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'account_contact_id',
																							'description' 	=>	'Id of the contact from which this sequence is attached, If email is not present in the contact with this account, then create the contact and use it\'s id.',
																							'arrMapping' 	=>	array(
																														'parentTable' 	=>	'account_contacts',
																														'key' 			=>	'id',
																														'description' 	=>	'&nbsp;'
																							 						)
																					 	),
																					array(
																							'name' 			=>	'csv_payload',
																							'description' 	=>	'Information from the CSV file related to this sequence in JSON formatted string.'
																					 	),
																					array(
																							'name' 			=>	'progress',
																							'description' 	=>	'0-Queued, 1-Sent, 2-Failed'
																					 	),
																					array(
																							'name' 			=>	'is_bounce',
																							'description' 	=>	'0-No, 1-Yes'
																					 	),
																					array(
																							'name' 			=>	'scheduled_at',
																							'description' 	=>	'Calculated scheduled time for this sequence, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'sent_at',
																							'description' 	=>	'Actual time when the mail gets fired, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'message_send_id',
																							'description' 	=>	'Id of message sent (received from mail server)'
																					 	),
																					array(
																							'name' 			=>	'sent_response',
																							'description' 	=>	'Mail sending attempt response from mail server'
																					 	),
																					array(
																							'name' 			=>	'locked',
																							'description' 	=>	'0-No, 1-Yes'
																					 	),
																					array(
																							'name' 			=>	'locked_date',
																							'description' 	=>	'Time staml when this sequence gets locked for the team member to do any action, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'open_count',
																							'description' 	=>	'Count of how many times this mails openes by the receipients.'
																					 	),
																					array(
																							'name' 			=>	'last_opened',
																							'description' 	=>	'When did the last time this email got opened by the receipients, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'replied',
																							'description' 	=>	'0-No, 1-Yesc'
																					 	),
																					array(
																							'name' 			=>	'last_replied',
																							'description' 	=>	'When the email was replied for the first time, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'reply_check_count',
																							'description' 	=>	'How many times reply has been checked'
																					 	),
																					array(
																							'name' 			=>	'reply_last_checked',
																							'description' 	=>	'When was the last time reply was checked, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'click_count',
																							'description' 	=>	'?????????????'
																					 	),
																					array(
																							'name' 			=>	'last_clicked',
																							'description' 	=>	'?????????????'
																					 	),
																					array(
																							'name' 			=>	'status',
																							'description' 	=>	'1-Active, 2-Deleted'
																					 	),
																					array(
																							'name' 			=>	'created',
																							'description'	=>	'Created Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	),
																					array(
																							'name' 			=>	'modified',
																							'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																					 	)
													 							)
				 								),
				'campaign_links' 	=>	array(
												'tableDescription' 	=>	'Stores and manages all the links shared in the content of email body in campaign sequences',
												'utility' 			=>	'Stores and manages all the links shared in the content of email body in campaign sequences',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Capaign Links Id, Primary Key',
																				 	),
																				array(
																						'name' 			=>	'account_id',
																						'description' 	=>	'Account id of the team member who created this campaign and added this link into the content of the mail',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_id',
																						'description' 	=>	'Id of the campaign of which this link belongs to.',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_stage_id',
																						'description' 	=>	'Id of the campaign stage og which this link belongs to',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_stages',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_sequence_id',
																						'description' 	=>	'Campaign sequence id to which this link belongs to',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_sequences',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'account_link_id',
																						'description' 	=>	'Id from the account link master table',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'account_link_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'redirect_key',
																						'description' 	=>	'Unique key generated to identify that the link belongs to this sequence of the campaign',
																				 	),
																				array(
																						'name' 			=>	'total_clicked',
																						'description' 	=>	'Total number of clicks receipents had clicked on the custom generated links.',
																				 	),
																				array(
																						'name' 			=>	'last_clicked',
																						'description' 	=>	'When the last time receipents clicked on this mail, In UNIX Timestamp, TimeZone (+00:00)',
																				 	),
																				array(
																						'name' 			=>	'modified',
																						'description'	=>	'Modified Date, In UNIX Timestamp, TimeZone (+00:00)'
																				 	)
												 							)
				 							),
				'campaign_track_history'	=>	array(
														'tableDescription' 	=>	'This table is used to track the actions taken by the receipients to the mail send by the campaign',
														'utility' 			=>	'This table is used to track the actions taken by the receipients to the mail send by the campaign',
														'columnDefination' 	=>	array(
																						array(
																								'name'			=>	'id',
																								'description' 	=>	'Campign Track history Id, Primary Key'
																						 	),
																						array(
																								'name'			=>	'campaign_sequence_id',
																								'description' 	=>	'Id of the sequence for tracking',
																								'arrMapping' 	=>	array(
																															'parentTable' 	=>	'campaign_sequences',
																															'key' 			=>	'id',
																															'description' 	=>	'&nbsp;'
																								 						)
																						 	),
																						array(
																								'name'			=>	'type',
																								'description' 	=>	'Type of action performed on the campaign mail. 1-Mail Open, 2-Mail Reply, 3-Link Click'
																						 	),
																						array(
																								'name'			=>	'acted_at',
																								'description' 	=>	'Action done on the campaign mail, In UNIX Timestamp, TimeZone (+00:00)'
																						 	),
																						array(
																								'name'			=>	'account_link_id',
																								'description' 	=>	'WHY??????????'
																						 	)
														 							)
				 									),
				'campaign_logs'		=>	array(
												'tableDescription' 	=>	'',
												'utility' 			=>	'',
												'columnDefination' 	=>	array(
																				array(
																						'name' 			=>	'id',
																						'description' 	=>	'Campaign Log Id, Primary Key'
																				 	),
																				array(
																						'name' 			=>	'campaign_id',
																						'description' 	=>	'Id of the campaign master',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_master',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_stage_id',
																						'description' 	=>	'Id of the current processing stage of the campaign',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_stages',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'campaign_sequence_id',
																						'description' 	=>	'Id of the campaign sequence current processing',
																						'arrMapping' 	=>	array(
																													'parentTable' 	=>	'campaign_sequences',
																													'key' 			=>	'id',
																													'description' 	=>	'&nbsp;'
																						 						)
																				 	),
																				array(
																						'name' 			=>	'log',
																						'description' 	=>	'Log Message'
																				 	),
																				array(
																						'name' 			=>	'log_type',
																						'description' 	=>	'1-Info, 2-Warning, 3-Error'
																				 	),
																				array(
																						'name' 			=>	'created',
																						'description' 	=>	''
																				 	)
												 							)
				 							)
			);



?><html>
	<head>
		<title>Saleshandy V2 - Database Doc</title>
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

		<!-- Optional theme -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
 		
 		<!-- Font awesome CDN -->
		<script src="https://use.fontawesome.com/ae80a7c647.js"></script>


		<style type="text/css">
			.eachContainer {
				background: #eee;
				border-radius: 10px;
				margin-bottom: 20px;
			}

			.parentContainer {
				margin-top: 25px;
			}

			.indexContainer {
				border: 2px solid #eee;
				margin-top: 50px;
			}

			.first_column {
				border-right: 1px solid #eee;
			}

			.top_button {
				border: 1px solid #eee;
			    width: 75px;
			    height: 75px;
			    position: fixed;
			    bottom: 25px;
			    right: 25px;
			    background: #ddd;
			    border-radius: 75px;
			}

			.top_button:hover {
				background: #afabab;
			}

			.top_button i {
				font-size: 50px;
			    position: relative;
			    left: 21px;
			    top: 8px;
			}
		</style>

	</head>
	<body>
		<a href="#indexContainer" title="Goto Top">
			<div class="top_button">
				<i class="fa fa-angle-up" aria-hidden="true"></i>
			</div>
		</a>

		<div class="container">
			<div class="row">
				<div class="col-sm-6 col-sm-offset-3 indexContainer" id="indexContainer">
					<table class="table">
						<thead>
							<td align="center" colspan="2">
								Index
							</td>
						</thead>
						<tbody>
							<tr>
								<td align="center" class="first_column">Sr No.</td>
								<td align="center">Table Name</td>
							</tr><?php
 							
 							$count = 1;
							foreach ($arrMaster as $tableName => $arrTableInfo) {
								?><tr>
									<td align="center" class="first_column"><?php print $count++; ?></td>
									<td align="left"><a href="#<?php print $tableName; ?>" title="Goto table <?php print $tableName; ?>"><?php print $tableName; ?></a></td>
								</tr><?php
							}
						?></tbody>
					</table>
				</div>
			</div>
			<div class="row parentContainer" ><?php

				foreach ($arrMaster as $tableName => $tableInfo) {

					?><div class="col-sm-12 eachContainer">
						
						<h1>
							<label id="<?php print $tableName; ?>"><?php 
								print $tableName; 
							?></label>
						</h1>

						<h4><?php print $tableInfo['tableDescription']; ?></h4>

						<p><?php print $tableInfo['utility']; ?></p><?php
	 						

	 						if(isset($tableInfo['columnDefination']) && !empty($tableInfo['columnDefination'])) {

	 							$arrColumnDefination = $tableInfo['columnDefination'];

								?><table class="table table-condensed">
									<thead>
										<tr>
											<td width="20%">
												Column Name
											</td>
											<td>
												Defination
											</td>
										</tr>
									</thead>
									<tbody><?php

										foreach ($arrColumnDefination as $columnIndex => $arrColumnDefination) {
											?><tr>
												<td>
													<label id="<?php print $tableName . "_" . $arrColumnDefination['name']; ?>"><?php
														print $arrColumnDefination['name'];	
													?></label>
												</td>
												<td><?php
													print $arrColumnDefination['description'];

													if(isset($arrColumnDefination['arrMapping']) && !empty($arrColumnDefination['arrMapping'])) {
														$arrMapping = $arrColumnDefination['arrMapping'];

														if(isset($arrMapping['parentTable']) && !empty($arrMapping['parentTable']) && 
															isset($arrMapping['key']) && !empty($arrMapping['key'])) {

															$parentTable = $arrMapping['parentTable'];
															$key = $arrMapping['key'];
															$description = "";
															
															if(isset($arrMapping['description']) && !empty($arrMapping['description'])) {
																$description = $arrMapping['description'];
															}

															if(!empty($description)) {
																?><br />

																<div class='alert alert-success'>
																	<!-- Link towards parent table -->
																	<a href="#<?php print $parentTable . "_" . $key; ?>" title=""><?php 
																		print $parentTable . "." . $key;
																	?></a><?php 

																	print " - " . $description;

																?></div><?php
															}
														}
													}
												?></td>
											</tr><?php
										}
									?></tbody>
								</table><?php
	 						}
						?><hr>
					</div><?php
				}
			?></div>
		</div>
	</body>
</html>