#define STATE_START_PULSES      0
#define STATE_FIRST_BIT         1
#define STATE_DATA              2
#define PIN                     15

#define DBFILE                  "database/database.sqlite"
#define ARTISAN_PATH            "artisan"

#include <limits.h>
#include <pigpio.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sqlite3.h>
#include <time.h>
#include <unistd.h>

#if defined(__APPLE__)
#include <mach-o/dyld.h>
#endif

#ifndef PATH_MAX
#define PATH_MAX 4096
#endif

unsigned int volatile start_pulse_counter=0,detection_state=0,last_interrupt_millis;
unsigned int data_array_index=0,data_array[13],shift_value=0,short_bit=0,add_1st_bit=1,current_byte=0;
unsigned int current_bit=1,bit_count=0,save_array[13],last_db_write,cookID,probe1_array[6],probe2_array[6];
unsigned int bit_ok,i,pin_state,firstRead=1,goodData,badReadCount,time_since_last;
int probe1=0,probe2=0,prevProbe1=0,prevProbe2=0,rc,current_micros,current_millis;
char *zErrMsg=0;
unsigned int shortBitTick,transmissionCount,nibbleShift,nibbleOne,nibbleTwo;
uint32_t toCheck,tsl_micros,last_interrupt_micros, checksum;

sqlite3 *db;
static volatile sig_atomic_t shutting_down = 0;

uint16_t shiftreg(uint16_t currentValue) {
    uint8_t msb = (currentValue >> 15) & 1;
    currentValue <<= 1;
    if (msb == 1) {
        currentValue ^= 0x1021;
    }
    return currentValue;
}

uint16_t calculate_checksum(uint32_t data) {
    uint16_t mask = 0x3331;
    uint16_t csum = 0x0;
    int i = 0;
    for(i = 0; i < 24; ++i) {
        if((data >> i) & 0x01) {
          csum ^= mask;
        }
        mask = shiftreg(mask);
    }
    return csum;
}

unsigned int quart(unsigned int param) {
	param &= 0x0F;
	if (param==0x05)
		return(0);
        if (param==0x06)
                return(1);
        if (param==0x09)
                return(2);
        if (param==0x0A)
                return(3);
        return 0;
}

static int callback(void *data, int argc, char **argv, char **colName) {
        (void)data;
        (void)argc;
        (void)argv;
        (void)colName;
        return 0;
}

static int get_executable_dir(char *dir_out, size_t dir_size) {
        char exe_path[PATH_MAX];

#if defined(__linux__)
        ssize_t len = readlink("/proc/self/exe", exe_path, sizeof(exe_path) - 1);
        if (len <= 0) {
                return 0;
        }
        exe_path[len] = '\0';
#elif defined(__APPLE__)
        uint32_t size = sizeof(exe_path);
        if (_NSGetExecutablePath(exe_path, &size) != 0) {
                return 0;
        }
#else
        return 0;
#endif

        char *slash = strrchr(exe_path, '/');
        if (slash == NULL) {
                return snprintf(dir_out, dir_size, ".") < (int) dir_size;
        }

        if (slash == exe_path) {
                return snprintf(dir_out, dir_size, "/") < (int) dir_size;
        }

        *slash = '\0';

        return snprintf(dir_out, dir_size, "%s", exe_path) < (int) dir_size;
}

static int change_to_project_root(void) {
        char root[PATH_MAX];
        const char *bbq_root = getenv("BBQ_ROOT");

        if (bbq_root != NULL && bbq_root[0] != '\0') {
                if (chdir(bbq_root) != 0) {
                        fprintf(stderr, "Failed to chdir to BBQ_ROOT=%s\n", bbq_root);
                        return 0;
                }
        } else if (! get_executable_dir(root, sizeof(root)) || chdir(root) != 0) {
                fprintf(stderr, "Failed to chdir to the maverick install directory.\n");
                return 0;
        }

        if (access(ARTISAN_PATH, F_OK) != 0) {
                fprintf(stderr, "artisan not found in project root.\n");
                return 0;
        }

        if (access(DBFILE, F_OK) != 0) {
                fprintf(stderr, "Database not found at %s\n", DBFILE);
                return 0;
        }

        if (getcwd(root, sizeof(root)) != NULL) {
                printf("Project root: %s\n", root);
        }

        return 1;
}

static int default_smoker_id(void) {
        sqlite3_stmt *stmt = NULL;
        int smoker_id = 1;

        if (sqlite3_prepare_v2(db, "SELECT id FROM smokers ORDER BY id LIMIT 1", -1, &stmt, NULL) != SQLITE_OK) {
                return smoker_id;
        }

        if (sqlite3_step(stmt) == SQLITE_ROW) {
                smoker_id = sqlite3_column_int(stmt, 0);
        }

        sqlite3_finalize(stmt);

        return smoker_id;
}

static void finish_cook(void) {
        char sql[160];
        char ended_at[20];
        time_t now = time(NULL);

        if (cookID == 0 || db == NULL) {
                return;
        }

        strftime(ended_at, sizeof(ended_at), "%Y-%m-%d %H:%M:%S", localtime(&now));

        snprintf(
                sql,
                sizeof(sql),
                "UPDATE cooks SET ended_at = '%s', updated_at = '%s' WHERE id = %d AND ended_at IS NULL;",
                ended_at,
                ended_at,
                cookID
        );

        rc = sqlite3_exec(db, sql, callback, 0, &zErrMsg);
        if (rc != SQLITE_OK) {
                printf("SQL error ending cook: %s\n", zErrMsg);
                sqlite3_free(zErrMsg);
                zErrMsg = 0;
                return;
        }
}

static void handle_shutdown(int signum) {
        (void)signum;
        shutting_down = 1;
}

void outputData() {
        unsigned int i=0;
	int secs, mics;
	gpioTime(PI_TIME_RELATIVE,&secs,&mics);

	if (firstRead==0) {
		if (goodData==1) {
			badReadCount=0;
			prevProbe1=probe1;
			prevProbe2=probe2;
		}
	}

        if ((save_array[0] == 0xAA) &&
            (save_array[1] == 0x99) &&
            (save_array[2] == 0x95) &&
            (save_array[3] == 0x59)) {

		probe1 = probe2 = 0;
                probe2_array[0]= quart(save_array[8] & 0x0F);
                probe2_array[1]= quart(save_array[8] >> 4);
                probe2_array[2]= quart(save_array[7] & 0x0F);
                probe2_array[3]= quart(save_array[7] >> 4);
                probe2_array[4]= quart(save_array[6] & 0x0F);
                probe1_array[0]= quart(save_array[6] >> 4);
                probe1_array[1]= quart(save_array[5] & 0x0F);
                probe1_array[2]= quart(save_array[5] >> 4);
                probe1_array[3]= quart(save_array[4] & 0x0F);
                probe1_array[4]= quart(save_array[4] >> 4);

                for (i=0;i<=4;i++) {
			probe1 += probe1_array[i] * (1<<(2*i));
                        probe2 += probe2_array[i] * (1<<(2*i));
                }

                probe1 -= 532;
                probe1 = (((probe1 * 9)/5) + 32);

                probe2 -= 532;
                probe2 = (((probe2 * 9)/5) + 32);

		if (probe1>=858992500 || probe1<0) {
		 probe1=0;
		}
		if (probe2>=858992500 || probe2<0) {
			probe2=0;
		}

		goodData=1;
		if (firstRead==0) {
			if ((probe1<(prevProbe1-20)) || (probe1>(prevProbe1+20))) {
				badReadCount++;
				if (badReadCount<3) {
					goodData=0;
					printf("Bad data #%d - Probe 1:%d\tPrevProbe 1:%d\t@%d\n",badReadCount,probe1,prevProbe1,secs);
				} else {
					goodData=1;
				}
			} else if ((probe2<(prevProbe2-20)) || (probe2>(prevProbe2+20))) {
				badReadCount++;
				if (badReadCount<3) {
					goodData=0;
					printf("Bad data #%d - Probe 2:%d\tPrevProbe 2:%d\t@%d\n",badReadCount,probe2,prevProbe2,secs);
				} else {
					goodData=1;
				}
			}
		}

		firstRead=0;

                printf("Probe 1:%d\tProbe 2:%d\t@%d\n",probe1,probe2,secs);

		char sql[160];
                time_t now = time(NULL);
		char buff[20];
                strftime(buff, sizeof(buff), "%Y-%m-%d %H:%M:%S", localtime(&now));

		if (goodData==1) {
			if (last_db_write==0 || (secs-last_db_write>=10)) {
				snprintf(
                                        sql,
                                        sizeof(sql),
                                        "INSERT INTO readings (cook_id, time, probe_food, probe_bbq) VALUES (%d, '%s', %d, %d);",
                                        cookID,
                                        buff,
                                        probe1,
                                        probe2
                                );
				printf("%s\n",sql);
				rc=sqlite3_exec(db,sql,callback,0,&zErrMsg);
				if (rc!=SQLITE_OK) {
					printf("SQL error: %s\n",zErrMsg);
                                        sqlite3_free(zErrMsg);
                                        zErrMsg = 0;
				} else {
					last_db_write=secs;
				}
			}
		}
        }
}

void readPin (int gpio,int pin_state,uint32_t tick) {
	(void)gpio;

	int secs,mics;
	gpioTime(PI_TIME_RELATIVE,&secs,&mics);

	bit_ok = 0;
        tsl_micros=(tick-last_interrupt_micros);
        last_interrupt_micros=tick;

        if (detection_state == STATE_START_PULSES) {
		if (((tsl_micros>=4900 && tsl_micros<=5100) || (start_pulse_counter>6 && tsl_micros>=4700 && tsl_micros<=5100)) && pin_state==1) {
                        start_pulse_counter++;
                        if (start_pulse_counter==8) {
                                printf("Possible preamble detected @%d\n",tick);
                                start_pulse_counter=0;
                                detection_state=STATE_FIRST_BIT;
                        } else if (start_pulse_counter>0) {
				printf("*TRIGGER* Since last pulse: %dms (%dus), Time from start: %ds, Pulse count:%d \n",tsl_micros/1000,tsl_micros,secs, start_pulse_counter);
			}
                } else if (tsl_micros>400) {
			if (start_pulse_counter>=6) {
				if (transmissionCount==3) {
					transmissionCount=0;
				}
				transmissionCount++;
			}
			if (start_pulse_counter>4) {
				printf("*FAIL* Since last pulse: %dms (%dus), Time from start: %ds, Pulse count:%d,trancount: %d\n",tsl_micros/1000,tsl_micros,secs, start_pulse_counter,transmissionCount);
			}
                        start_pulse_counter=0;
                }
	}
        if (detection_state==STATE_FIRST_BIT && pin_state==1) {
                detection_state=STATE_DATA;
                current_bit=1;
                current_byte=0;
		nibbleOne=0;
		nibbleTwo=0;
                shift_value=0;
                data_array_index=0;
                bit_ok=0;
                short_bit=0;
                add_1st_bit = 1;
                bit_count = 1;
		if (transmissionCount==3) {
			transmissionCount=0;
		}
		transmissionCount++;
		toCheck=0;
		checksum=0;
                printf("Set first bit, going DATA@%d, trancount: %d\n",tick,transmissionCount);
        }

        else if (detection_state==STATE_DATA) {
		if (add_1st_bit==0 || (tsl_micros>=400 && tsl_micros<=580)) {
                if ((tsl_micros > 150) && (tsl_micros<=400)) {
                        if (short_bit == 0) {
				shortBitTick=tsl_micros;
                                short_bit = 1;
                        } else {
				shortBitTick=0;
                                bit_count++;
                                short_bit = 0;
                                bit_ok = 1;
                                current_bit=pin_state;
                        }
                }

                if ((tsl_micros>400) && (tsl_micros < 600)) {
                        if (short_bit == 1) {
                                detection_state = STATE_START_PULSES;
                                printf("!!!PATTERN FAILURE!!! @%d since last: %d on bit #%d byte #%d - short bit tick was %d\n",tick,tsl_micros,bit_count,data_array_index,shortBitTick);
				shortBitTick=0;
                                short_bit=0;

			} else {
	                        bit_count++;
                	        current_bit=pin_state;
                        	bit_ok = 1;
			}
                }

                if (bit_ok) {
                        if (add_1st_bit) {
				nibbleOne=0x01;
                                shift_value = 1;
				nibbleShift=1;
                                add_1st_bit = 0;
                        }

			if (shift_value<=3) {
	                        nibbleOne=(nibbleOne<<1)+current_bit;
			} else {
	                        nibbleTwo=(nibbleTwo<<1)+current_bit;
			}

			if (nibbleShift>=25 && nibbleShift<=48) {
				toCheck=(toCheck<<1)+current_bit;
				printf("toCheck is 0x%X at %d (bit is %d)\n",toCheck,nibbleShift,current_bit);
			}

			if (nibbleShift>=49 && nibbleShift<=72) {
				checksum=(checksum<<1)+current_bit;
				printf("checksum is 0x%X at %d (bit is %d)\n",checksum,nibbleShift,current_bit);
			}

                        shift_value++;
			nibbleShift++;

			if (shift_value==4) {
				if (nibbleOne!=0x5 &&
				    nibbleOne!=0x6 &&
				    nibbleOne!=0x9 &&
				    nibbleOne!=0xA) {
					printf("bad nibble one value, resetting\n");
	                                detection_state = STATE_START_PULSES;
					shortBitTick=0;
                	                short_bit=0;
				}
			} else if (shift_value==8) {
				if (nibbleTwo!=0x5 &&
				    nibbleTwo!=0x6 &&
				    nibbleTwo!=0x9 &&
				    nibbleTwo!=0xA) {
					printf("bad nibble two value, resetting\n");
	                                detection_state = STATE_START_PULSES;
					shortBitTick=0;
                	                short_bit=0;
				}
			}

                        if (shift_value == 8) {
				current_byte=(nibbleOne<<4)+nibbleTwo;
                                data_array[data_array_index++] = current_byte;
                                bit_count=0;
                                shift_value = 0;
                                current_byte = 0;
				nibbleOne=0;
				nibbleTwo=0;
                        }

                        if (data_array_index==13) {
                                start_pulse_counter = 0;
				detection_state = STATE_START_PULSES;
                                for (i=0;i<=12;i++) {
					if (i==0) {printf("Header: ");}
					else if (i==3) {printf("Startup: ");}
					else if (i==4) {printf("Temps: ");}
					else if (i==9) {printf("Checksum: ");}
					printf("0x%02X ",data_array[i]);
					if (i==2 || i==3 || i==8) {printf("\n");}
                                        save_array[i] = data_array[i];
                                }
				printf("\n");
				printf("toCheck: 0x%X\n",toCheck);
				printf("Calculated Checksum: 0x%X\n",calculate_checksum(toCheck));
				printf("Calculated Checksum: %d\n",calculate_checksum(toCheck));
				fflush(stdout);
				outputData();
                        }
                        bit_ok = 0;
                }
		} else {
			printf("skipping in STATE_DATA at %ds (%dus)\n",secs,tsl_micros);
		}
        }
	fflush(stdout);
}

static int start_cook(void) {
        char sql[256];
        char started_at[20];
        int smoker_id;
        time_t now = time(NULL);

        strftime(started_at, sizeof(started_at), "%Y-%m-%d %H:%M:%S", localtime(&now));
        smoker_id = default_smoker_id();

        snprintf(
                sql,
                sizeof(sql),
                "INSERT INTO cooks (smoker_id, title, created_at, updated_at, ended_at) "
                "VALUES (%d, 'Live Cook', '%s', '%s', NULL);",
                smoker_id,
                started_at,
                started_at
        );

        rc = sqlite3_exec(db, sql, callback, 0, &zErrMsg);
        if (rc != SQLITE_OK) {
                printf("SQL error inserting into cooks: %s\n", zErrMsg);
                sqlite3_free(zErrMsg);
                zErrMsg = 0;
                return 0;
        }

        cookID = (unsigned int) sqlite3_last_insert_rowid(db);
        printf("Cook ID is %d\n", cookID);

        return 1;
}

int main(int argc, char **argv)
{
        (void)argc;
        (void)argv;

        signal(SIGTERM, handle_shutdown);
        signal(SIGINT, handle_shutdown);

        if (! change_to_project_root()) {
                return 1;
        }

	if (gpioInitialise()<0) {
	        printf("Failed to start gpio on BCM PIN %d\n",PIN);
		return 1;
	}
	printf("Starting on BCM PIN %d\n",PIN);

	if (gpioSetAlertFunc(PIN,readPin)>0) {
		printf("Failed to set alert on BCM PIN %d\n",PIN);
		return 1;
	}
	printf("Alert set on BCM PIN %d\n",PIN);

	rc=sqlite3_open(DBFILE,&db);
	if (rc!=SQLITE_OK) {
		printf("Can't open db: %s\n",sqlite3_errmsg(db));
                gpioTerminate();
                return 1;
	}

        printf("db opened\n");

        if (!start_cook()) {
                sqlite3_close(db);
                gpioTerminate();
                return 1;
        }

        while (!shutting_down) {
		gpioDelay(60000);
        }

        finish_cook();
	sqlite3_close(db);
        gpioTerminate();
        return 0;
}
