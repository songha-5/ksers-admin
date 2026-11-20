# KSERS 2027 참가 등록·관리자

KSERS 2027 국제학술대회의 회원, 참가 등록, 초록, 결제, 관리자 화면이다. PHP 8.3과 MySQL로 동작한다.

공개 홈페이지(`https://reg.ksers.kr/2027`)는 이 저장소가 아니다. 홈페이지는 별도 Next.js 프로젝트이고, 이 저장소는 등록·관리 서버다.
스테이징 서버 : `https://dev.reg.ksers.kr/2027`입니다
## 실행

이 프로젝트에는 `package.json`이 없다. `npm run dev`로 실행하지 않는다.

PHP와 MySQL이 필요하다. 저장소 루트에 `.env`를 만들고 아래 값을 채운 뒤 서버를 띄운다. `.env`는 Git에 포함되지 않는다.

```powershell
php -S 127.0.0.1:8080 -t .
```

- 참가자 로그인: http://127.0.0.1:8080/service/login.php
- 관리자: http://127.0.0.1:8080/admin/index.php

관리자 주소는 `member_info.is_admin = 1`인 계정으로 로그인한 뒤에만 열린다. 회원이 하나도 없고 `KSERS_SEED_EMAIL`, `KSERS_SEED_PASSWORD`, `KSERS_AES_KEY`가 있으면 첫 접속 때 그 이메일로 관리자 계정이 하나 만들어진다.

## 환경변수

| 이름 | 용도 |
|---|---|
| `MYSQLHOST` | MySQL 호스트. 없으면 `localhost` |
| `MYSQLPORT` | MySQL 포트. 없으면 `3306` |
| `MYSQLDATABASE` | 데이터베이스 이름. 없으면 `railway` |
| `MYSQLUSER` | 데이터베이스 사용자 |
| `MYSQLPASSWORD` | 데이터베이스 비밀번호 |
| `KSERS_AES_KEY` | 회원 비밀번호 암호화 키 |
| `KSERS_SEED_EMAIL` | 최초 관리자 이메일 |
| `KSERS_SEED_PASSWORD` | 최초 관리자 비밀번호 |
| `KSERS_ABSTRACT_DIR` | 초록 파일 디렉터리. 없으면 `/data/abstracts` |
| `KSERS_PROGRAM_DIR` | 프로그램 연자 파일 디렉터리. 없으면 `/data/program` |
| `KSERS_MEMBER_UPLOAD_DIR` | 참가자 제출 파일 디렉터리. 없으면 `/data/member_uploads` |
| `KSERS_VIDEO_DIR` | 영상 디렉터리. 없으면 `/data/member_videos`. 종류별 하위 폴더에 저장한다 |
| `KSERS_PUBLIC_BASE` | 파일 공개 주소와 비밀번호 재설정 링크의 기준. 비어 있으면 요청 호스트를 쓴다 |
| `RAILWAY_ENVIRONMENT_NAME` | Railway가 배포할 때 넣는다. `staging`이면 홈페이지 링크가 dev.ksers.kr을 가리킨다. |
| `BREVO_SMTP_HOST` | Brevo SMTP 서버. 없으면 `smtp-relay.brevo.com` |
| `BREVO_SMTP_PORT` | Brevo SMTP 포트. 없으면 `587` |
| `BREVO_SMTP_USER` | Brevo SMTP 로그인 메일 |
| `BREVO_SMTP_KEY` | Brevo SMTP 키. 로컬에서 SMTP로 보낼 때 쓴다 |
| `BREVO_API_KEY` | Brevo API 키. 있으면 SMTP 대신 HTTPS로 보낸다. Railway에서는 이 값이 필요하다 |
| `BREVO_FROM` | Brevo에서 인증한 보내는 주소. 비어 있으면 대회 설정의 보내는 주소를 쓴다 |
| `BREVO_FROM_NAME` | 보내는 이름. 없으면 `KSERS` |

데이터베이스가 비어 있으면 첫 접속 때 `schema.sql`로 테이블을 만든다.

## 구조

```text
admin/                 관리자 화면
  page/member/         회원 목록
  page/registration/   참가 등록·결제 조회
  page/abstract/       초록 조회와 심사 상태
  page/submission/     참가자 제출 파일
  page/program/        프로그램·연자 입력
  page/setting/        대회 공지, 계좌, 참가비, 초록 분야
service/               참가자 화면
  login.php            로그인
  join.php             회원가입
  registration.php     사전등록
  abstract.php         초록 제출
  mypage.php           회원정보, 무통장 결제, 서류
  payment.php          무통장 결제 저장
  auth.json.php        홈페이지 로그인 코드 확인
common.php             데이터베이스 연결과 로컬 .env 로드
ksers_app.php          대회 설정, 참가비, 프로그램 파일 경로
ksers_auth.php         홈페이지 복귀 주소와 일회용 로그인 코드
ksers_submission.php   참가자 파일 종류와 용량
schema.sql             회원, 등록, 초록, 결제, 프로그램 테이블
```

참가자 화면의 위쪽 메뉴는 홈, 사전등록, 초록 제출, CV·영상 제출, 마이페이지다. 관리자 계정에는 CV·영상 제출이 보이지 않는다. 프로그램·연자 화면은 관리자 메뉴에만 있다.

## 홈페이지 로그인

홈페이지와 이 서버는 도메인이 다르다. 비밀번호는 이 서버의 로그인 화면에서 입력한다. 로그인한 계정은 이 서버의 세션에만 있다. 홈페이지가 회원 번호를 보내도 세션을 만들거나 바꾸지 않는다.

1. 홈페이지는 `/service/login.php?next=https://ksers.kr/2027/...`로 보낸다.
2. 로그인에 성공하면 세션에 그 계정의 `seq`와 로그인 시각이 들어간다. 이미 그 브라우저에 세션이 있으면 비밀번호 화면 없이 같은 계정으로 돌아간다.
3. `next` 주소로 돌아가며 `code`가 붙는다. 코드는 5분 동안 한 번만 쓸 수 있다.
4. 홈페이지는 `GET /service/auth.json.php?code=...`로 확인한다.
5. 응답은 `loggedIn`, `role`(`member` 또는 `admin`), `name`, `seq`, `expiresAt`이다. `seq`와 `expiresAt`은 화면 표시용이다. 이 값으로 다시 로그인할 수 없다.
6. 마이페이지는 `/service/mypage.php`, 초록은 `/service/abstract.php`, CV·영상은 `/service/materials.php`이다. 주소에 `seq`를 붙이지 않는다. 저장은 세션의 계정으로만 들어간다.
7. 로그아웃은 `/service/logout.php?next=https://ksers.kr/2027/...`이다. 세션 쿠키를 지운다.
8. 로그인 후 30분이 지나면 세션의 계정을 지운다. 그 뒤에는 마이페이지와 업로드가 로그인 화면으로 간다.

`next`는 `https://ksers.kr`과 `https://*.ksers.kr`(예: `https://dev.ksers.kr`), 로컬 개발의 `http://localhost:3000`, `http://127.0.0.1:3000`만 허용한다. 경로는 `/2027`부터 `/2035`까지다.

## 배포

운영은 Railway 서비스에서 Docker 이미지로 실행한다. `Dockerfile`이 PHP 내장 서버를 8080 포트로 띄운다. 업로드 파일은 `/data` 볼륨에 둔다.

로컬 `.env`는 배포에 포함하지 않는다. 운영 값은 Railway 환경변수에 둔다.
