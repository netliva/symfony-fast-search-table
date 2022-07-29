<?php
namespace Netliva\SymfonyFastSearchBundle\Events;


final class NetlivaFastSearchEvents
{
	/**
	 * Veriler cache'e kaydolurken her bir kayıt için çalışır
	 *
	 * @Event("Netliva\SymfonyFastSearchBundle\Events\PrepareRecordEvent")
	 */
	const  PREPARE_RECORD = 'netliva_fast_search.prepare_record';

}
