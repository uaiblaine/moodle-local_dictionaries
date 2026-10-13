# search(): the parent constraint stops naming the parent DOMAIN (the always-true IN list keeps the
# :parentdomainid placeholder bound and typed).
s~p\.domainid = :parentdomainid~p.domainid IN (:parentdomainid, p.domainid)~s;
