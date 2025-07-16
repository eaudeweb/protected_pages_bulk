# Protected Pages Scheduler

The Protected Pages Scheduler submodule extends the functionality of the Protected Pages module by introducing automated password removal scheduling.

This module implements hook_cron to periodically check and remove password protection from pages at a scheduled time.

Content managers often create pages that are password-protected until a specific time. However, if this time falls outside working hours, manually removing the protection can be inconvenient. This module eliminates that hassle by allowing automatic password removal at a predefined time.

The scheduler enables password removal with a precision of up to one minute. However, for optimal accuracy, the CRON job must be configured correctly. If CRON is not set to run every minute, the actual removal time may be delayed by up to X minutes, where X is the CRON execution interval.
