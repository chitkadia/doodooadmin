<?php

CLASS COMMONVARS {

	public static function arrLogFileNames() {
		return array(
						'1'		=>	'adminusers',
						'2'		=>	'teammemberusers',
						'3'		=>	'billingInfo',
						'4' 	=> 	'emailaccounts',
						'5'		=>	'companies',
						'6'		=>	'contacts',
						'7' 	=>	'documents',
						'8'		=>	'documentlinks',
						'9'		=>	'direct_generated_documentlinks',
						// '10'	=>	'document_visits',
						'10'	=>	'templates',
						'11'	=>	'emails',
						'12'	=>	'emailrecipient',
						'13'	=>	'campaigns',
						'14'	=>	'activities',
						'15'	=> 	'exit'
						// '15'	=>	'importstripedata'
		 			);
	}

	public static function arrPricingPlanMapping() {
		
		return array(
						'RegularM' 		=>	'REGULAR_MONTHLY',
						'PlusM' 		=>	'PLUS_MONTHLY',
						'EnterpriseM' 	=>	'ENTERPRISE_MONTHLY',
						'RegularY' 		=>	'REGULAR_YEARLY',
						'PlusY' 		=>	'PLUS_YEARLY',
						'EnterpriseY' 	=>	'ENTERPRISE_YEARLY'
		 			);
	}


	public static function arrDemoCompanyInfomation() {

		return array(
	 					'name' 	=>	array(
	 										'JD Demo',
	 										'RobWill Demo',
	 										'Danleh Demo',
	 										'JD Inc',
	 										'Danleh AG',
	 										'RobWill Ltd'
	 					 				),
	 					'email'	=>	array(
	 										'jd@jdinc.com',
	 										'rob@robwill.com',
	 										'daneiel@danleh.com'
	 					 				)
		 			);

	}

	public static function arrDemoContactInformation() {

		return array(
						'name' 		=>	array(
												'John Demo',
												'Robin Demo',
												'Daniel Demo',
												'John Doe',
												'Robin Williams',
												'Daniel Lehmann'
											),
						'email'		=>	array(
												'jd@jdinc.com',
		 										'rob@robwill.com',
		 										'daneiel@danleh.com'
											)
		 			);
	}

	public static function contactGroupMasterTableName() {
		return 'tmp_contact_group_master';
	}

	public static function contactGroupTableName() {
		return 'tmp_contact_group';
	}

	public static function arrEmailSendingMethods() {

		return array(
						'1'	=> 	'4',
						'2'	=> 	'3',
						'3'	=> 	'1',
						'4'	=> 	'2'
	 				);

	}
}

?>