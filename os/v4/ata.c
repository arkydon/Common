#include "ctype.h"
#include "ata.h"
// GO
void read_pio(dword lba, byte * buffer, size_t size) {
	ATA_PRIMARY_CLI();
	ATA_PRIMARY_WAIT_WHILE_BUSY();
	ATA_PRIMARY_SET_LBA(lba, 1, ATA_MASTER);
	ATA_PRIMARY_SEND_COMMAND(0x20);
	asm("cld \n rep insw"::"D"(buffer), "d"(ATA_PRIMARY_DATA_PORT), "c"(size / sizeof(word)));
	ATA_PRIMARY_STI();
}
