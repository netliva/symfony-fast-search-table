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
    
	/**
	 * Veriler ekrana basılmadan önceki sıralama işleminden hemen önce verilerin düzenlenmesi için
	 *
	 * @Event("Netliva\SymfonyFastSearchBundle\Events\BeforeViewEvent")
	 */
	const  BEFORE_SORTING = 'netliva_fast_search.before_sorting';

	/**
	 * Veriler ekrana basılmadan hemen önce verilerin düzenlenmesi için
	 *
	 * @Event("Netliva\SymfonyFastSearchBundle\Events\BeforeViewEvent")
	 */
	const  BEFORE_VIEW = 'netliva_fast_search.before_show';

}
