# coding=utf-8
import urllib, re, sys, threading, cookielib, urllib2
from BeautifulSoup import BeautifulSoup
### Головная функция
def main():
    agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)'
    aCarnage = Carnage('YourNick', 'YourPass', 'arkaim.carnage.ru', 'cp1251', agent)
    aCarnage.login()
    me = aCarnage.inf(aCarnage.user)
    # Если ранен - выйти
    if me['inj']:
        aCarnage.logout()
        exit(1)
    aCarnage.urlopen('main.pl')
    # Подождать пока здоровье восстановится
    while(me['hp_wait']):
        time.sleep(me['hp_wait'])
        me = aCarnage.inf(aCarnage.user)
    # Найти подходящую заявку
    aCarnage.find()
    me = aCarnage.inf(aCarnage.user)
    # Если заявка состоялась - в бой!
    if me['battle']: aCarnage.fight()
    # После боя - выход из игры
    aCarnage.logout()
class Opener:
    def __init__(self, host, encoding, agent):
        self.host = host
        self.encoding = encoding
        self.agent = agent
        self.opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookielib.CookieJar()))
    def urlopen(self, goto, data = None):
        f = self.opener.open(urllib2.Request(
            "http://%s/%s" % (self.host, goto),
            self.urlencode(data),
            {"User-agent" : self.agent}
        ))
        result = f.read()
        self.soup = BeautifulSoup(result)
    def urlencode(self, data):
        if data is None: return None
        for key in data:
            data[key] = data[key].encode(self.encoding, 'ignore')
        return urllib.urlencode(data)
class CarnageBot(Opener):
    ### Конструктор принимает логин, пароль, кодировку (cp1251) и идентификатор браузера
    def __init__(self, user, password, host, encoding, agent):
        self.user = user
        self.password = password
        Opener.__init__(self, host, encoding, agent)
    ### Получить информацию об игроке - например о самом себе
    def inf(self, user):
        self.urlopen('inf.pl?' + self.urlencode({'user': user}))
        # Кол-во жизни
        onmouseover = self.soup.find('img', onmouseover = re.compile(unicode('Уровень жизни:', 'utf8')))
        m = re.search('([0-9]+).([0-9]+)', onmouseover['onmouseover'])
        hp = int(m.group(1))
        hp_max = int(m.group(2))
        hp_wait = (100 - (hp * 100) / hp_max) * 18
        # Уровень
        td = self.soup.find('td', text = re.compile(unicode('Уровень:', 'utf8')))
        level = int(td.next.string)
        # Ранен или нет
        inj = 0
        if self.soup.find(src = re.compile(unicode('travma.gif', 'utf8'))): inj = 1
        # В бою или нет
        battle = 0
        if self.soup.find(text = re.compile(unicode('Персонаж находится в бою', 'utf8'))): battle = 1
        hero = {'level': level, 'hp': hp, 'hp_max': hp_max, 'hp_wait': hp_wait, 'inj': inj, 'battle': battle}
        return hero
    ### Войти в игру
    def login(self):
        data = {'action': 'enter', 'user_carnage': self.user, 'pass_carnage': self.password}
        self.urlopen('enter.pl', data)
        self.urlopen('main.pl')
    ### Выйти из игры
    def logout(self):
        self.urlopen('main.pl?action=exit')
    ### В бой!!!
    def fight(self):
        self.urlopen('battle.pl')
        while True:
            # Добить по таймауту
            if self.soup.find(text = re.compile(unicode('Противник потерял сознание', 'utf8'))):
                self.urlopen('battle.pl?cmd=timeout&status=win')
                break
            if self.soup.find(text = re.compile(unicode('Бой закончен.', 'utf8'))):
                break
            reg = re.compile(unicode('Для вас бой закончен. Ждите окончания боя.', 'utf8'))
            if self.soup.find(text = reg):
                break
            cmd = self.soup.find('input', {'name' : 'cmd', 'type' : 'hidden'})
            to = self.soup.find('input', {'name' : 'to', 'type' : 'hidden'})
            # Есть ли по кому бить?!
            if cmd and to:
                a = random.randint(1, 4)
                b0 = random.randint(1, 4)
                b1 = random.randint(1, 4)
                while b1 == b0: b1 = random.randint(1, 4)
                pos = 2
                arg = (cmd['value'], to['value'], pos, a, a, b0, b0, b1, b1)
                self.urlopen('battle.pl?cmd=%s&to=%s&pos=%s&A%s=%s&D%s=%s&D%s=%s' % arg)
            else:
                self.urlopen('battle.pl')
                time.sleep(random.randint(5, 30))
    ### Найти заявку - подающий заявку должен быть на 1 уровень ниже нашего
    def find(self):
        me = self.inf(self.user)
        while True:
            v = ''
            self.urlopen('zayavka.pl?cmd=haot.show')
            reg = re.compile(unicode('Текущие заявки на бой', 'utf8'))
            script = self.soup.find('fieldset', text = reg).findNext('script')
            m = re.findall('.*', script.string)
            for value in m:
                if value.find('group.gif') < 0: continue
                if value.find('(%i-%i)' % (me['level'] - 2, me['level'])) < 0: continue
                t = re.search(',([0-9]{1,2}),u', value)
                if not t: continue
                t = int(t.group(1))
                v = re.search('tr\(([0-9]+)', value).group(1)
                print 'Found battle t=%i v=%s' % (t, v)
                break
            if v: break
            time.sleep(80)
        nd = self.soup.find('input', {'name' : 'nd', 'type' : 'hidden'})
        self.urlopen('zayavka.pl?cmd=haot.accept&nd=%s&battle_id=%s' % (nd['value'], v))
        time.sleep(t + 30)
if __name__ == '__main__': main()
