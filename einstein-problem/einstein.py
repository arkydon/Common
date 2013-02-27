# coding=utf-8
import itertools
p = itertools.permutations
# GO
for NAC in p(range(1, 6), 5):
    ANG, DAT, SVED, NOR, GERM = NAC
    # 1. Норвежец живёт в первом доме.
    if NOR != 1: continue
    for COLOR in p(range(1, 6), 5):
        RED, GREEN, YELLOW, BLUE, WHITE = COLOR
        # 2. Англичанин живёт в красном доме.
        if ANG != RED: continue
        # 3. Зелёный дом находится слева от белого, рядом с ним.
        if GREEN != WHITE - 1: continue
        # 12. Норвежец живёт рядом с синим домом.
        if abs(NOR - BLUE) != 1: continue
        for PET in p(range(1, 6), 5):
            DOG, CAT, HORSE, BIRD, FISH = PET
            # 11. Швед выращивает собак.
            if SVED != DOG: continue
            # 13. Тот, кто выращивает лошадей, живёт в синем доме.
            if HORSE != BLUE: continue
            for DRINK in p(range(1, 6), 5):
                WATER, BEER, MILK, COFE, TEA = DRINK
                # 8. Тот, кто живёт в центре, пьет молоко.
                if MILK != 3: continue
                # 15. В зелёном доме пьют кофе.
                if GREEN != COFE: continue
                # 4. Датчанин пьет чай.
                if DAT != TEA: continue
                for CIGAR in p(range(1, 6), 5):
                    WINFIELD, DUNHILL, MARLBORO, PM, ROTHMANS = CIGAR
                    # 10. Тот, кто курит Pall Mall, выращивает птиц.
                    if PM != BIRD: continue
                    # 6. Тот, кто живёт в жёлтом доме, курит Dunhill.
                    if YELLOW != DUNHILL: continue
                    # 5. Тот, кто курит Marlboro, живёт рядом с тем, кто выращивает кошек.
                    if abs(MARLBORO - CAT) != 1: continue
                    # 14. Тот, кто курит Winfield, пьет пиво.
                    if BEER != WINFIELD: continue
                    # 7. Немец курит Rothmans.
                    if GERM != ROTHMANS: continue
                    # 9. Сосед того, кто курит Marlboro, пьет воду.
                    if abs(MARLBORO - WATER) != 1: continue
                    # Print
                    list = [None, [], [], [], [], []]
                    list[ANG].append('ANG')
                    list[DAT].append('DAT')
                    list[SVED].append('SVED')
                    list[NOR].append('NOR')
                    list[GERM].append('GERM')
                    list[RED].append('RED')
                    list[GREEN].append('GREEN')
                    list[YELLOW].append('YELLOW')
                    list[BLUE].append('BLUE')
                    list[WHITE].append('WHITE')
                    list[DOG].append('DOG')
                    list[CAT].append('CAT')
                    list[HORSE].append('HORSE')
                    list[BIRD].append('BIRD')
                    list[FISH].append('FISH')
                    list[WATER].append('WATER')
                    list[BEER].append('BEER')
                    list[MILK].append('MILK')
                    list[COFE].append('COFE')
                    list[TEA].append('TEA')
                    list[WINFIELD].append('WINFIELD')
                    list[DUNHILL].append('DUNHILL')
                    list[MARLBORO].append('MARLBORO')
                    list[PM].append('PM')
                    list[ROTHMANS].append('ROTHMANS')
                    for i, j in enumerate(list): print i, j
                    # Exit
                    exit()
