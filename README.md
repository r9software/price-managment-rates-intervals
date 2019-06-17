# price-managment-rates-intervals
# Requirements


 - User is able to specify price depending on the dates. i.e. set the price $x for the
dates range $startDate - $endDate. Let’s call such ranges as intervals.
 - User can add as many intervals as he wants (using any start/end date).
 - System can’t have crossing intervals.
- New interval price have higher priority over existing ones.
- New interval can’t lead to changes in dates not belonged to its dates range.
- If user tries to save interval that interferes with existing ones in DB, system has to apply last user changes and modify -other intervals in order to apply requirement 3.
- Any intervals with the same price that can be merged (without gaps between)
should be merged.

# DB
You need to develop a system for saving prices in intervals. Each interval should have
such fields:
- date_start (date)
- date_end (date)
- price (float)

URL
http://www.midevs.com/API/price-managment/
